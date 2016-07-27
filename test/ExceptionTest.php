<?php
namespace Mayden\Pineapple\Test;

use PHPUnit\Framework\TestCase;
use Mayden\Pineapple\Exception;
use Mayden\Pineapple\Error;

class ExceptionTest extends TestCase
{
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
}
