<?php
namespace Pineapple\Test;

use Pineapple\DB;
use Pineapple\Test\DB\Driver\TestDriver;
use Pineapple\Error;
use Pineapple\DB\Error as DBError;
use PHPUnit\Framework\TestCase;

class DBTest extends TestCase
{
    public function testFactory()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertInstanceOf(TestDriver::class, $dbh);
    }

    public function testFactoryWithArrayOptions()
    {
        $dbh = DB::factory(TestDriver::class, ['debug' => 'q who?']);
        $this->assertInstanceOf(TestDriver::class, $dbh);
        $this->assertEquals('q who?', $dbh->getOption('debug'));
    }

    public function testFactoryWithBadDriver()
    {
        $dbh = DB::factory('murp');
        $this->assertInstanceOf(Error::class, $dbh);
    }

    public function testIsConnection()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertTrue(DB::isConnection($dbh));
    }

    public function testIsNotConnection()
    {
        $this->assertFalse(DB::isConnection(new \stdClass));
    }

    public function testErrorMessage()
    {
        $this->assertEquals('no error', DB::errorMessage(DB::DB_OK));
    }

    public function testErrorMessageFromErrorClass()
    {
        $error = new DBError(DB::DB_ERROR_NOT_FOUND);
        $this->assertEquals('not found', DB::errorMessage($error));
    }
}
