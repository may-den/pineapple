<?php
namespace Mayden\Pineapple\Test\DB;

use Mayden\Pineapple\DB;
use Mayden\Pineapple\DB\Result;
use Mayden\Pineapple\Test\DB\Driver\TestDriver;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testConstruct()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Result::class, $result);
    }

    public function testConstructWithOptions()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth, [
            'limit_from' => 10,
            'limit_count' => 20,
        ]);
        $this->assertInstanceOf(Result::class, $result);

        // ugly, but it's this or add methods to result which won't be used
        $reflectionClass = new \ReflectionClass($result);
        $reflectionProp = $reflectionClass->getProperty('limit_from');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(10, $reflectionProp->getValue($result));
    }

    public function testSetOption()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $result->setOption('limit_from', 12345);

        // ugly, but it's this or add methods to result which won't be used
        $reflectionClass = new \ReflectionClass($result);
        $reflectionProp = $reflectionClass->getProperty('limit_from');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(12345, $reflectionProp->getValue($result));
    }

    public function testFetchRow()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);

        $row = $result->fetchRow();
        $this->assertEquals([1, 'test1'], $row);
    }

    public function testFetchRowByAssoc()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);

        $row = $result->fetchRow(DB::DB_FETCHMODE_ASSOC);
        $this->assertEquals([
            'id' => 1,
            'data' => 'test1'
        ], $row);
    }

    public function testFetchRowByObject()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $expected = new \stdClass();
        $expected->id = 1;
        $expected->data = 'test1';

        $row = $result->fetchRow(DB::DB_FETCHMODE_OBJECT);
        $this->assertEquals($expected, $row);
    }

    public function testFetchRowWithLimit()
    {
        $this->markTestIncomplete('this test does not currently work (but should)');
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth, ['limit_from' => 5]);

        $row = $result->fetchRow();
        $this->assertEquals([1, 'test1'], $row);
    }
}
