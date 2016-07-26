<?php
namespace Mayden\Pineapple\Test;

use PHPUnit\Framework\TestCase;
use Mayden\Pineapple\Util;
use Mayden\Pineapple\Error;
use Mayden\Pineapple\Test\PseudoError;

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

    public function testIsError()
    {
        $util = new Util();
        $error = $util->raiseError('something broke');
        $this->assertTrue($util->isError($error));
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
}
