<?php
namespace Pineapple\Test;

use PHPUnit\Framework\TestCase;
use Pineapple\Exception;
use Pineapple\Error;
use Pineapple\Test\MonkeyPatching;
use Pineapple\Test\Exception\MonkeyTriggerErrorException;

class ExceptionTest extends TestCase
{
    private $observerCalled = false;

    public function testConstruct()
    {
        $e = new Exception('sommat broke');
        $this->assertInstanceOf(Exception::class, $e);
    }

    public function testConstructWithCode()
    {
        $e = new Exception('sommat broke', 54321);
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertEquals(54321, $e->getCode());
    }

    public function testConstructWithException()
    {
        $nestedException = new \Exception('burp');
        $e = new Exception('sommat broke', $nestedException);
        $this->assertEquals($nestedException, $e->getCause());
    }

    public function testConstructWithError()
    {
        $error = new Error('burp');
        $e = new Exception('sommat broke', $error);
        $this->assertEquals($error, $e->getCause());
    }

    public function testConstructWithInvalidObject()
    {
        $this->expectException(Exception::class);
        $e = new Exception('sommat broke', new \stdClass());
    }

    public function testConstructWithArrayWithoutMessage()
    {
        $e = new Exception('sommat broke', [1, 2, 3, 4, 5]);
        $this->assertEquals([1, 2, 3, 4, 5], $e->getCause());
    }

    public function testConstructWithArrayWithMessage()
    {
        $e = new Exception('sommat broke', ['message' => 'bobbins']);
        $this->assertEquals([['message' => 'bobbins']], $e->getCause());
    }

    public function pseudoObserver(Exception $e)
    {
        $this->observerCalled = true;
    }

    public function testAddObserverWithoutLabel()
    {
        Exception::addObserver([$this, 'pseudoObserver']);

        $reflectionClass = new \ReflectionClass(Exception::class);
        $reflectionProp = $reflectionClass->getProperty('observers');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(['default' => [$this, 'pseudoObserver']], $reflectionProp->getValue());
    }

    public function testRemoveObserverWithoutLabel()
    {
        Exception::addObserver([$this, 'pseudoObserver']);

        $reflectionClass = new \ReflectionClass(Exception::class);
        $reflectionProp = $reflectionClass->getProperty('observers');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(['default' => [$this, 'pseudoObserver']], $reflectionProp->getValue());

        Exception::removeObserver();

        $this->assertEquals([], $reflectionProp->getValue());
    }

    public function testAddObserverWithLabel()
    {
        Exception::addObserver([$this, 'pseudoObserver'], 'bonzo');

        $reflectionClass = new \ReflectionClass(Exception::class);
        $reflectionProp = $reflectionClass->getProperty('observers');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(['bonzo' => [$this, 'pseudoObserver']], $reflectionProp->getValue());
    }

    public function testRemoveObserverWithLabel()
    {
        Exception::addObserver([$this, 'pseudoObserver'], 'bonzo');

        $reflectionClass = new \ReflectionClass(Exception::class);
        $reflectionProp = $reflectionClass->getProperty('observers');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(['bonzo' => [$this, 'pseudoObserver']], $reflectionProp->getValue());

        Exception::removeObserver('bonzo');

        $this->assertEquals([], $reflectionProp->getValue());
    }

    public function testGetUniqueId()
    {
        $reflectionClass = new \ReflectionClass(Exception::class);
        $reflectionProp = $reflectionClass->getProperty('uniqueid');
        $reflectionProp->setAccessible(true);

        $expectedId = $reflectionProp->getValue();
        $this->assertEquals($expectedId, Exception::getUniqueId());
        $this->assertEquals($expectedId + 1, $reflectionProp->getValue());
    }

    public function testGetErrorData()
    {
        $e = new Exception('sommat broke');
        $this->assertEquals([], $e->getErrorData());
    }

    public function testGetCause()
    {
        $nestedException = new \Exception('emergency kittens');
        $e = new Exception('sommat bwoke', $nestedException);
        $this->assertEquals($nestedException, $e->getCause());
    }

    public function testGetCauseMessageWithNoCause()
    {
        // what an intricate nest of uncomment code getCauseMessage is.
        $causes = null;
        $e = new Exception('sommat broke');
        $e->getCauseMessage($causes);

        $this->assertEquals([[
            'class' => Exception::class,
            'message' => 'sommat broke',
            'file' => 'unknown',
            'line' => 'unknown'
        ]], $causes);
    }

    public function testGetCauseMessageWithPlainException()
    {
        $causes = null;
        $nestedException = new \Exception('emergency puppies');
        $e = new Exception('green wing', $nestedException);
        $e->getCauseMessage($causes);

        $this->assertEquals([[
            'class' => Exception::class,
            'message' => 'green wing',
            'file' => 'unknown',
            'line' => 'unknown'
        ], [
            'class' => \Exception::class,
            'message' => 'emergency puppies',
            'file' => __FILE__,
            'line' => $nestedException->getLine(),
        ]], $causes);
    }

    public function testGetCauseMessageWithOurException()
    {
        $causes = null;
        $nestedException = new Exception('emergency puppies');
        $e = new Exception('green wing', $nestedException);
        $e->getCauseMessage($causes);

        $this->assertEquals([[
            'class' => Exception::class,
            'message' => 'green wing',
            'file' => 'unknown',
            'line' => 'unknown'
        ], [
            'class' => Exception::class,
            'message' => 'emergency puppies',
            'file' => 'unknown',
            'line' => 'unknown'
        ]], $causes);
    }

    public function testGetCauseMessageWithError()
    {
        $causes = null;
        $nestedError = new Error('ack');
        $e = new Exception('green wing', $nestedError);
        $e->getCauseMessage($causes);

        $this->assertEquals([[
            'class' => Exception::class,
            'message' => 'green wing',
            'file' => 'unknown',
            'line' => 'unknown'
        ], [
            'class' => Error::class,
            'message' => 'ack',
            'file' => 'unknown',
            'line' => 'unknown'
        ]], $causes);
    }

    public function testGetCauseMessageWithArray()
    {
        // original code could have done with some recursion. then these tests could be simpler.
        $causes = null;
        $nestedException = new Exception('emergency puppies');
        $nestedError = new Error('ack');
        $nestedRootException = new \Exception('how many fingers am i holding up');

        $e = new Exception('green wing', [
            $nestedException,
            $nestedError,
            $nestedRootException,
            [
                'package' => 'pineapple',
                'message' => 'everything is awesome',
                'context' => [
                    'file' => __FILE__,
                    'line' => 12345,
                ]
            ]
        ]);

        $e->getCauseMessage($causes);

        $this->assertEquals([[
            'class' => Exception::class,
            'message' => 'green wing',
            'file' => 'unknown',
            'line' => 'unknown'
        ], [
            'class' => Exception::class,
            'message' => 'emergency puppies',
            'file' => 'unknown',
            'line' => 'unknown'
        ], [
            'class' => Error::class,
            'message' => 'ack',
            'file' => 'unknown',
            'line' => 'unknown'
        ], [
            'class' => \Exception::class,
            'message' => 'how many fingers am i holding up',
            'file' => __FILE__,
            'line' => $nestedRootException->getLine(),
        ], [
            'class' => 'pineapple',
            'message' => 'everything is awesome',
            'file' => __FILE__,
            'line' => 12345,
        ]], $causes);
    }

    public function testGetTraceSafe()
    {
        // in all seriousness, i have no idea why this method exists in the class
        $e = new Exception('uh oh');
        $trace = $e->getTraceSafe();
        $this->assertEquals(__CLASS__, $trace[0]['class']);
    }

    public function testGetErrorClass()
    {
        // in all seriousness, i have no idea why this method exists in the class
        $e = new Exception('uh oh');
        $errorClass = $e->getErrorClass();
        $this->assertEquals(__CLASS__, $errorClass);
    }

    public function testGetErrorMethod()
    {
        $e = new Exception('uh oh');
        $errorMethod = $e->getErrorMethod();
        $this->assertEquals(__FUNCTION__, $errorMethod);
    }

    public function testToString()
    {
        unset($_SERVER['REQUEST_URI']); // we want to avoid any possibly html output
        $e = new Exception('garg. i am saying garg.');
        $exceptionString = $e->__toString();
        $this->assertRegExp('/^' . preg_quote(Exception::class) . ': garg. i am saying garg. in/', $exceptionString);
    }

    public function testToText()
    {
        $e = new Exception('i have powers pinto beans can only dream of.');
        $exceptionString = $e->toText();
        $this->assertRegExp(
            '/^' . preg_quote(Exception::class) . ': i have powers pinto beans can only dream of. in/',
            $exceptionString
        );
    }

    public function testToStringMagic()
    {
        unset($_SERVER['REQUEST_URI']); // we want to avoid any possibly html output
        $e = new Exception('you fried cyclops.');
        $exceptionString = (string) $e;
        $this->assertRegExp('/^' . preg_quote(Exception::class) . ': you fried cyclops. in/', $exceptionString);
    }

    public function backtraceFodder(array $fodder, $food)
    {
        if (is_callable($food)) {
            call_user_func($food);
        }
        if (!empty($fodder)) {
            $nextFood = array_shift($fodder);
            $this->backtraceFodder($fodder, $nextFood);
        }
    }

    public function testToStringHtml()
    {
        // generate some backtrace fodder - the last item triggers the real test execution
        $this->backtraceFodder([
            null,
            [1, 2, 3],
            new \stdClass(),
            true,
            (int) 1,
            (double) 1.1,
            (string) 'goat',
            (string) 'this is a really long string, greater than 16 characters',
            [$this, 'realTestToStringHtml'],
        ], null);
    }

    public function realTestToStringHtml()
    {
        // this sort of test is really an output test, and best suited to characterisation, so let's just test a few
        // things to ensure that the output contains something like the exception we generated.

        $_SERVER['REQUEST_URI'] = 'http://localhost/'; // force html output

        // generate the exception and get the html string
        $e = new Exception('i have no kiwis.');
        $exceptionString = $e->__toString();

        // ensure the output is a table
        $this->assertRegExp('/^\<table/', $exceptionString);

        // look for our exception message and class
        $this->assertRegExp(
            '#' . preg_quote('<b>' . Exception::class . '</b>: i have no kiwis. in') . '#',
            $exceptionString
        );

        // method backtraceFodder should have been called number_of_elements_in_array + 1
        $this->assertEquals(10, substr_count($exceptionString, 'backtraceFodder'));

        // unset the request string from superglobal
        unset($_SERVER['REQUEST_URI']);
    }

    public function testConstructWithObserverSignal()
    {
        Exception::addObserver([$this, 'pseudoObserver']);
        $this->observerCalled = false;

        $e = new Exception('far from the evil toenails of doom.');

        $this->assertTrue($this->observerCalled);
        $this->observerCalled = false;
        Exception::removeObserver();
    }

    public function testConstructWithPrintObserver()
    {
        Exception::addObserver(Exception::OBSERVER_PRINT);
        $this->expectOutputString('rise up and bare your biscuit filthy fangs at the oppressive leash wielding demon.');
        $e = new Exception('rise up and bare your biscuit filthy fangs at the oppressive leash wielding demon.');
        Exception::removeObserver();
    }

    public function testConstructWithTriggerObserver()
    {
        Exception::addObserver(Exception::OBSERVER_TRIGGER);
        $this->expectException(MonkeyTriggerErrorException::class);
        $this->expectExceptionMessage('parts break after overuse');
        $e = new Exception('parts break after overuse');
        Exception::removeObserver();
    }

    public function testConstructWithBadObserver()
    {
        Exception::addObserver('spoon');
        $this->expectException(MonkeyTriggerErrorException::class);
        $this->expectExceptionMessage('invalid observer type');
        $e = new Exception('meow! meow! meow! cat chow!!');
    }
}
