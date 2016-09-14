<?php
namespace Pineapple\Test;

use PHPUnit\Framework\TestCase;
use Pineapple\Util;
use Pineapple\Error;
use Pineapple\Exception;

class ErrorTest extends TestCase
{
    private $errors = [];
    private $callbackMessageTrap = null;

    public function testConstruct()
    {
        $error = new Error();
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testConstructWithMode()
    {
        $error = new Error(null, null, Util::PEAR_ERROR_RETURN);
        $this->assertInstanceOf(Error::class, $error);
        $this->assertEquals(Util::PEAR_ERROR_RETURN, $error->getMode());
    }

    private function setErrorHandler()
    {
        $this->errors = [];
        set_error_handler([$this, 'errorHandler']);
    }

    private function restoreErrorHandler()
    {
        restore_error_handler();
    }

    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $this->errors[] = [$errno, $errstr, $errfile, $errline, $errcontext];
    }

    public function testConstructWithException()
    {
        $this->setErrorHandler();
        /**
         * n.b. this is deliberate. phpunit translates warnings/notices into exceptions, which
         * causes an issue because we cannot expect two exceptions, and after one has been caught
         * execution in that function stops. so we're temporarily changing the error handler, then
         * capturing the trigger_error() that is made in Error class, and checking we only have one
         * warning/notice
         */
        try {
            $error = new Error(null, null, Util::PEAR_ERROR_EXCEPTION);
            // if we got this far then an exception wasn't thrown
            $this->fail('an expected excpetion was not thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
        $this->restoreErrorHandler();

        $this->assertEquals(1, count($this->errors), 'Expected one error, but got: ' . json_encode($this->errors));

        $this->assertContains('PEAR_ERROR_EXCEPTION is obsolete, use class Pineapple\Exception for exceptions', $this->errors[0]);
    }

    public function errorCallback(Error $error)
    {
        $this->callbackMessageTrap = $error->getMessage();
    }

    public function testConstructWithCallback()
    {
        $this->callbackMessageTrap = null;
        $error = new Error('test callback', null, Util::PEAR_ERROR_CALLBACK, [$this, 'errorCallback']);
        $this->assertEquals('test callback', $this->callbackMessageTrap);
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testConstructPrintWithoutFormatString()
    {
        $this->expectOutputString('test print');
        $error = new Error('test print', null, Util::PEAR_ERROR_PRINT);
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testConstructPrintWithFormatString()
    {
        $this->expectOutputString('foo test print bar');
        $error = new Error('test print', null, Util::PEAR_ERROR_PRINT, 'foo %s bar');
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testConstructWithTrigger()
    {
        $this->setErrorHandler();
        $error = new Error('test trigger error', null, Util::PEAR_ERROR_TRIGGER);
        $this->restoreErrorHandler();

        $this->assertEquals(1, count($this->errors), 'Expected one error, but got: ' . json_encode($this->errors));

        $this->assertContains('test trigger error', $this->errors[0]);
    }

    public function testGetMode()
    {
        $error = new Error();
        $this->assertEquals(Util::PEAR_ERROR_RETURN, $error->getMode());
    }

    public function testGetCallback()
    {
        $this->callbackMessageTrap = null;
        $error = new Error('test callback', null, Util::PEAR_ERROR_CALLBACK, [$this, 'errorCallback']);
        $this->assertEquals([$this, 'errorCallback'], $error->getCallback());
    }

    public function testGetMessage()
    {
        $error = new Error('tomem hardipsum royor sit joshet, oliversectetur');
        $this->assertEquals('tomem hardipsum royor sit joshet, oliversectetur', $error->getMessage());
    }

    public function testGetCode()
    {
        $error = new Error('boop', 12345);
        $this->assertEquals(12345, $error->getCode());
    }

    public function testGetType()
    {
        $error = new Error();
        $this->assertEquals(Error::class, $error->getType());
    }

    public function testGetUserInfo()
    {
        $error = new Error(null, null, null, null, [1, 2, 3, 4, 5]);
        $this->assertEquals([1, 2, 3, 4, 5], $error->getUserInfo());
    }

    public function testGetDebugInfo()
    {
        $error = new Error(null, null, null, null, [1, 2, 3, 4, 5]);
        $this->assertEquals([1, 2, 3, 4, 5], $error->getDebugInfo());
    }

    public function testGetBacktrace()
    {
        $bt = debug_backtrace();
        $error = new Error();
        $this->assertEquals($bt, array_slice($error->getBacktrace(), 0 - count($bt)));
    }

    public function testGetBacktraceWithFrame()
    {
        $bt = debug_backtrace();
        $error = new Error();
        $frame = $error->getBacktrace(1);
        // if this is not equal, phpunit will try to dump the expect/actual, so compare ourselves
        $this->assertTrue($bt[0] === $frame, 'backtrace frames did not match');
    }

    public function testAddUserInfo()
    {
        $error = new Error();
        $error->addUserInfo('lemming');
        $this->assertEquals('lemming', $error->getUserInfo());
    }

    public function testAddUserInfoTwice()
    {
        $error = new Error();
        $error->addUserInfo('lemming');
        $error->addUserInfo('weasel');
        $this->assertEquals('lemming ** weasel', $error->getUserInfo());
    }

    public function testClassStringCast()
    {
        $error = new Error('stoat');
        $this->assertEquals('stoat', (string) $error);
    }

    public function testToString()
    {
        $error = new Error('otter');
        $this->assertEquals('[' . strtolower(Error::class) . ': message="otter" code=0 mode=return level=notice prefix="" info=""]', $error->toString());
    }

    public function testToStringWithMethodCallback()
    {
        $this->callbackMessageTrap = null;
        $error = new Error('test callback', null, Util::PEAR_ERROR_CALLBACK, [$this, 'errorCallback']);
        $this->assertEquals('[' . strtolower(Error::class) . ': message="test callback" code=0 mode=callback callback=' . strtolower(get_class()) . '::errorCallback prefix="" info=""]', $error->toString());
    }

    public function testToStringWithBasicCallback()
    {
        // use strtolower as a parameter-eating noop
        $error = new Error('test callback', null, Util::PEAR_ERROR_CALLBACK, 'strtolower');
        $this->assertEquals('[' . strtolower(Error::class) . ': message="test callback" code=0 mode=callback callback=strtolower prefix="" info=""]', $error->toString());
    }

    public function testToStringWithPrint()
    {
        $this->expectOutputString('test print');
        $error = new Error('test print', null, Util::PEAR_ERROR_PRINT);
        $this->assertEquals('[' . strtolower(Error::class) . ': message="test print" code=0 mode=print level=notice prefix="" info=""]', $error->toString());
    }

    public function testToStringWithTrigger()
    {
        $this->setErrorHandler();
        $error = new Error('test trigger error', null, Util::PEAR_ERROR_TRIGGER);
        $this->restoreErrorHandler();

        $this->assertEquals(1, count($this->errors), 'Expected one error, but got: ' . json_encode($this->errors));

        $this->assertEquals('[' . strtolower(Error::class) . ': message="test trigger error" code=0 mode=trigger level=notice prefix="" info=""]', $error->toString());

    }
}
