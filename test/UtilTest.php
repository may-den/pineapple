<?php
namespace Mayden\Pineapple\Test;

use PHPUnit\Framework\TestCase;
use Mayden\Pineapple\Util;
use Mayden\Pineapple\Error;

class UtilTest extends TestCase
{
    public function testConstruct()
    {
        $util = new Util();
        $this->assertInstanceOf(Util::class, $util);

        // ugly, but it's this or add methods to util which won't be used
        $reflectionClass = new \ReflectionClass($util);
        $reflectionProp = $reflectionClass->getProperty('error_class');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(Error::class, $reflectionProp->getValue($util));
    }

    public function testConstructWithErrorClass()
    {
        $util = new Util(PseudoError::class);
        $this->assertInstanceOf(Util::class, $util);

        // ugly, but it's this or add methods to util which won't be used
        $reflectionClass = new \ReflectionClass($util);
        $reflectionProp = $reflectionClass->getProperty('error_class');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(PseudoError::class, $reflectionProp->getValue($util));
    }

    public function testRaiseError()
    {
        $util = new Util();
        $error = $util->raiseError('something broke');
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testRaiseErrorStatic()
    {
        $error = Util::raiseError('something broke');
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testRaiseErrorWithoutMessage()
    {
        $util = new Util();
        $error = $util->raiseError('something broke', 12345, null, null, null, null, true);
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testIsError()
    {
        $util = new Util();
        $error = $util->raiseError('something broke');
        $this->assertTrue($util->isError($error));
    }

    public function testIsErrorIsNotAnError()
    {
        $util = new Util();
        $error = new \stdClass();
        $this->assertFalse($util->isError($error));
    }

    public function testIsErrorByMessage()
    {
        $util = new Util();
        $error = $util->raiseError('something broke');
        $this->assertTrue($util->isError($error, 'something broke'));
    }

    public function testIsErrorByCode()
    {
        $util = new Util();
        $error = $util->raiseError('something broke', 54321);
        $this->assertTrue($util->isError($error, 54321));
    }

    public function testIsErrorStatic()
    {
        $error = Util::raiseError('something broke');
        $this->assertTrue(Util::isError($error));
    }

    public function testThrowError()
    {
        $util = new Util();
        $error = $util->throwError('something broke');
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testThrowErrorWithObject()
    {
        $util = new Util();

        $error = $util->throwError(Util::raiseError('something broke'));
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testThrowErrorStatic()
    {
        $error = Util::throwError('something broke');
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testThrowErrorStaticWithObject()
    {
        $error = Util::throwError(Util::raiseError('something broke'));
        $this->assertInstanceOf(Error::class, $error);
    }
}
