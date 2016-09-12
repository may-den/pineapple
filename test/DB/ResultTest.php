<?php
namespace Mayden\Pineapple\Test\DB;

use Mayden\Pineapple\DB;
use Mayden\Pineapple\DB\Result;
use Mayden\Pineapple\DB\Row;
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

    public function testFetchRowByRowObject()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $dbh->setFetchMode(DB::DB_FETCHMODE_OBJECT, Row::class);

        $sth = $dbh->simpleQuery('SELECT things FROM a_table');

        $result = new Result($dbh, $sth);
        $fakeRow = [
            'id' => 1,
            'data' => 'test1',
        ];
        $expected = new Row($fakeRow);

        $row = $result->fetchRow(DB::DB_FETCHMODE_OBJECT);
        $this->assertEquals($expected, $row);
    }

    public function testFetchRowWithLimit()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        // i am 99% sure this is a bug in Result, that if from is specified without count,
        // then the result is null. not fixing as it's probably been there for ~10 years,
        // and it's likely everyone who has encountered that bug has accounted for it, yet
        // if they haven't they are probably now relying on the 'null' it is returning.
        $result = new Result($dbh, $sth, [
            'limit_from' => 1,
            'limit_count' => 5,
        ]);

        $row = $result->fetchRow();
        $this->assertEquals([1, 'test1'], $row);
    }

    public function testFetchRowWithLimitBeyondAvailableResults()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');

        $result = new Result($dbh, $sth, [
            'limit_from' => 5,
            'limit_count' => 5,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $row = $result->fetchRow(); // rip out the first five rows
            $this->assertInternalType('array', $row);
        }

        $row = $result->fetchRow();
        $this->assertNull($row);
    }

    public function testFetchRowAutofreeWithLimit()
    {
        $dbh = DB::connect(TestDriver::class . '://', ['autofree' => true]);
        $dbh->resetFreeFlag();
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        // i am 99% sure this is a bug in Result, that if from is specified without count,
        // then the result is null. not fixing as it's probably been there for ~10 years,
        // and it's likely everyone who has encountered that bug has accounted for it, yet
        // if they haven't they are probably now relying on the 'null' it is returning.
        $result = new Result($dbh, $sth, [
            'limit_from' => 1,
            'limit_count' => 1,
        ]);

        $this->assertFalse($dbh->getFreeFlag());
        $row = $result->fetchRow();
        $this->assertFalse($dbh->getFreeFlag());
        $this->assertInternalType('array', $row);
        $row = $result->fetchRow();
        $this->assertTrue($dbh->getFreeFlag());
        $this->assertNull($row);
    }

    public function testFetchRowAutofreeWithoutLimit()
    {
        $dbh = DB::connect(TestDriver::class . '://', ['autofree' => true]);
        $dbh->resetFreeFlag();
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        // i am 99% sure this is a bug in Result, that if from is specified without count,
        // then the result is null. not fixing as it's probably been there for ~10 years,
        // and it's likely everyone who has encountered that bug has accounted for it, yet
        // if they haven't they are probably now relying on the 'null' it is returning.
        $result = new Result($dbh, $sth);

        $this->assertFalse($dbh->getFreeFlag());
        for ($i = 0; $i < 20; $i++) {
            $row = $result->fetchRow();
            $this->assertInternalType('array', $row);
        }
        $this->assertFalse($dbh->getFreeFlag());
        $row = $result->fetchRow();
        $this->assertTrue($dbh->getFreeFlag());
        $this->assertNull($row);
    }
}
