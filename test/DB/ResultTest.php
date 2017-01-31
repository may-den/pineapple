<?php
namespace Pineapple\Test\DB;

use Pineapple\DB;
use Pineapple\DB\Result;
use Pineapple\DB\Row;
use Pineapple\DB\Error;
use Pineapple\Test\DB\Driver\TestDriver;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testConstruct()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Result::class, $result);
    }

    public function testConstructWithOptions()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth, [
            'limit_from' => 10,
            'limit_count' => 20,
        ]);
        $this->assertInstanceOf(Result::class, $result);

        // ugly, but it's this or add methods to result which won't be used
        $reflectionClass = new \ReflectionClass($result);
        $reflectionProp = $reflectionClass->getProperty('limitFrom');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(10, $reflectionProp->getValue($result));
    }

    public function testSetOption()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $result->setOption('limit_from', 12345);

        // ugly, but it's this or add methods to result which won't be used
        $reflectionClass = new \ReflectionClass($result);
        $reflectionProp = $reflectionClass->getProperty('limitFrom');
        $reflectionProp->setAccessible(true);

        $this->assertEquals(12345, $reflectionProp->getValue($result));
    }

    public function testFetchRow()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);

        $row = $result->fetchRow();
        $this->assertEquals([1, 'test1'], $row);
    }

    public function testFetchRowByAssoc()
    {
        $dbh = DB::factory(TestDriver::class);
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
        $dbh = DB::factory(TestDriver::class);
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
        $dbh = DB::factory(TestDriver::class);
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
        $dbh = DB::factory(TestDriver::class);
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
        $dbh = DB::factory(TestDriver::class);
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
        $dbh = DB::factory(TestDriver::class, ['autofree' => true]);
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
        $dbh = DB::factory(TestDriver::class, ['autofree' => true]);
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

    public function testFetchInto()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);

        $row = null;
        $result = $result->fetchInto($row);
        $this->assertEquals([1, 'test1'], $row);
    }

    public function testFetchIntoByAssoc()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);

        $row = null;
        $result = $result->fetchInto($row, DB::DB_FETCHMODE_ASSOC);
        $this->assertEquals([
            'id' => 1,
            'data' => 'test1'
        ], $row);
    }

    public function testFetchIntoByObject()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $expected = new \stdClass();
        $expected->id = 1;
        $expected->data = 'test1';

        $row = null;
        $result = $result->fetchInto($row, DB::DB_FETCHMODE_OBJECT);
        $this->assertEquals($expected, $row);
    }

    public function testFetchIntoByRowObject()
    {
        $dbh = DB::factory(TestDriver::class);
        $dbh->setFetchMode(DB::DB_FETCHMODE_OBJECT, Row::class);

        $sth = $dbh->simpleQuery('SELECT things FROM a_table');

        $result = new Result($dbh, $sth);
        $fakeRow = [
            'id' => 1,
            'data' => 'test1',
        ];
        $expected = new Row($fakeRow);

        $row = null;
        $result = $result->fetchInto($row, DB::DB_FETCHMODE_OBJECT);
        $this->assertEquals($expected, $row);
    }

    public function testFetchIntoWithLimit()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        // i am 99% sure this is a bug in Result, that if from is specified without count,
        // then the result is null. not fixing as it's probably been there for ~10 years,
        // and it's likely everyone who has encountered that bug has accounted for it, yet
        // if they haven't they are probably now relying on the 'null' it is returning.
        $result = new Result($dbh, $sth, [
            'limit_from' => 1,
            'limit_count' => 5,
        ]);

        $row = null;
        $result = $result->fetchInto($row);
        $this->assertEquals([1, 'test1'], $row);
    }

    public function testFetchIntoWithLimitBeyondAvailableResults()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');

        $result = new Result($dbh, $sth, [
            'limit_from' => 5,
            'limit_count' => 5,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $row = $result->fetchRow(); // rip out the first five rows
            $this->assertInternalType('array', $row);
        }

        $row = null;
        $result = $result->fetchInto($row);
        $this->assertNull($row);
    }

    public function testFetchIntoAutofreeWithLimit()
    {
        $dbh = DB::factory(TestDriver::class, ['autofree' => true]);
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
        $row = null;
        $fetchResult = $result->fetchInto($row);
        $this->assertFalse($dbh->getFreeFlag());
        $this->assertInternalType('array', $row);
        $row = null;
        $fetchResult = $result->fetchInto($row);
        $this->assertTrue($dbh->getFreeFlag());
        $this->assertNull($row);
    }

    public function testFetchIntoAutofreeWithoutLimit()
    {
        $dbh = DB::factory(TestDriver::class, ['autofree' => true]);
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
        $row = null;
        $result = $result->fetchInto($row);
        $this->assertTrue($dbh->getFreeFlag());
        $this->assertNull($row);
    }

    public function testNumCols()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertEquals(2, $result->numCols());
    }

    public function testNumRows()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertEquals(20, $result->numRows());
    }

    public function testNumRowsWithPortability()
    {
        // tempted to rip out numRows' 'portability' facility entirely.
        // really, it should be avoided if at all possible. i hope it is never used.
        $dbh = DB::factory(TestDriver::class, ['portability' => DB::DB_PORTABILITY_NUMROWS]);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);

        $this->assertEquals(20, $result->numRows());
    }

    public function testNumRowsWithPortabilityAndPreparedStatements()
    {
        $dbh = DB::factory(TestDriver::class, ['portability' => DB::DB_PORTABILITY_NUMROWS]);
        // "enable" prepared queries
        $dbh->setPrepareFeature(true);
        $sth = $dbh->prepare('SELECT things FROM a_table WHERE foo = ?');
        $result = $dbh->execute($sth, ['bar']);

        $this->assertEquals(20, $result->numRows());

        // testdriver doesn't implement bound parameters, so the tokeniser expands the query.
        // also, there's no getter to pull the last query out of dbh, so fetch directly.
        $this->assertEquals('SELECT things FROM a_table WHERE foo = \'bar\'', $dbh->getLastQuery());
    }

    public function testNumRowsWithPortabilityQueryFailure()
    {
        $dbh = DB::factory(TestDriver::class, ['portability' => DB::DB_PORTABILITY_NUMROWS]);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $dbh->simpleQuery('BREAK DELIBERATELY');
        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Error::class, $result->numRows());
    }

    public function testNextResult()
    {
        // this tests the next query in sequence for stacked queries.
        // testdriver does not implement this, but the pricinple stands.
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertFalse($result->nextResult());
    }

    public function testFree()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');

        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->free());
    }

    public function testFreeWithDbError()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('BREAKINGSELECT things FROM a_table');

        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertInstanceOf(Error::class, $result->free());
    }

    public function testTableInfo()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals([
            [
                'table' => 'bonzotable',
                'name' => 'bonzoname',
                'type' => 'integer',
                'len' => '12',
                'flags' => 'NOT NULL',
            ],
            [
                'table' => 'bonzotable',
                'name' => 'banjoname',
                'type' => 'string',
                'len' => '20',
                'flags' => '',
            ]
        ], $result->tableInfo());
    }

    public function testTableInfoWithModeString()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertInstanceOf(Result::class, $result);

        $tableInfo = $result->tableInfo('NO WAY');
        $this->assertInstanceOf(Error::class, $tableInfo);
        $this->assertEquals('DB Error: insufficient data supplied', $tableInfo->getMessage());
    }

    public function testGetQuery()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth);
        $this->assertEquals('SELECT things FROM a_table', $result->getQuery());
    }

    public function testGetRowCounter()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->simpleQuery('SELECT things FROM a_table');
        $result = new Result($dbh, $sth, [
            'limit_from' => 5,
            'limit_count' => 5,
        ]);

        $this->assertNull($result->getRowCounter());
        $result->fetchRow();
        $this->assertEquals(6, $result->getRowCounter());
    }
}
