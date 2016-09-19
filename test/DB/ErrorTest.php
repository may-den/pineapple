<?php
namespace Pineapple\Test\DB;

use Pineapple\DB;
use Pineapple\DB\Error;
use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    public function testConstruct()
    {
        $error = new Error();
        $this->assertInstanceOf(Error::class, $error);
    }

    public function testConstructWithCode()
    {
        $error = new Error(DB::DB_ERROR_NOT_FOUND);
        $this->assertEquals(DB::DB_ERROR_NOT_FOUND, $error->getCode());
    }

    public function testConstructWithErrorString()
    {
        $error = new Error('i are failed');
        $this->assertEquals(DB::DB_ERROR, $error->getCode());
        $this->assertEquals('DB Error: i are failed', $error->getMessage());
    }
}
