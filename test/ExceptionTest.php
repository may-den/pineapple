<?php
namespace Mayden\Pineapple\Test;

use PHPUnit\Framework\TestCase;
use Mayden\Pineapple\Exception;
use Mayden\Pineapple\Error;

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
}
