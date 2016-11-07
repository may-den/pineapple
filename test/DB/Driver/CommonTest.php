<?php
namespace Pineapple\Test\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Row;
use Pineapple\DB\Result;
use Pineapple\DB\Error;
use Pineapple\DB\Driver\Common;
use Pineapple\Test\DB\Driver\TestDriver;
use Pineapple\DB\Exception\FeatureException;

use PHPUnit\Framework\TestCase;

// 'Common' is an abstract class so we're going to use our mock TestDriver to stub
// how we access the class and its methods

class CommonTest extends TestCase
{
    private static $orderedAllData = [
        [1, 'test1'],
        [2, 'test2'],
        [3, 'test3'],
        [4, 'test4'],
        [5, 'test5'],
        [6, 'test6'],
        [7, 'test7'],
        [8, 'test8'],
        [9, 'test9'],
        [10, 'test10'],
        [11, 'test11'],
        [12, 'test12'],
        [13, 'test13'],
        [14, 'test14'],
        [15, 'test15'],
        [16, 'test16'],
        [17, 'test17'],
        [18, 'test18'],
        [19, 'test19'],
        [20, 'test20'],
    ];

    private static $orderedArrayData = [
        1 => ['test1'],
        2 => ['test2'],
        3 => ['test3'],
        4 => ['test4'],
        5 => ['test5'],
        6 => ['test6'],
        7 => ['test7'],
        8 => ['test8'],
        9 => ['test9'],
        10 => ['test10'],
        11 => ['test11'],
        12 => ['test12'],
        13 => ['test13'],
        14 => ['test14'],
        15 => ['test15'],
        16 => ['test16'],
        17 => ['test17'],
        18 => ['test18'],
        19 => ['test19'],
        20 => ['test20']
    ];

    private static $assocData = [
        1 => 'test1',
        2 => 'test2',
        3 => 'test3',
        4 => 'test4',
        5 => 'test5',
        6 => 'test6',
        7 => 'test7',
        8 => 'test8',
        9 => 'test9',
        10 => 'test10',
        11 => 'test11',
        12 => 'test12',
        13 => 'test13',
        14 => 'test14',
        15 => 'test15',
        16 => 'test16',
        17 => 'test17',
        18 => 'test18',
        19 => 'test19',
        20 => 'test20'
    ];

    public function testConstruct()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertInstanceOf(Common::class, $dbh);
    }

    public function testSleepWakeup()
    {
        // __sleep is a magic method, use serialize to trigger it.
        // honestly i have no idea why it even exists. who freezes their db connection?
        $dbh = DB::factory(TestDriver::class);
        $rehydratedObject = unserialize(serialize($dbh));
        $this->assertEquals($dbh, $rehydratedObject);
    }

    public function testSleepWakeupConnected()
    {
        // __sleep is a magic method, use serialize to trigger it.
        // honestly i have no idea why it even exists. who freezes their db connection?
        $dbh = DB::factory(TestDriver::class);
        $dbh->stubConnect();
        $rehydratedObject = unserialize(serialize($dbh));
        $rehydratedObject->stubConnect();
        $this->assertEquals($dbh, $rehydratedObject);
    }

    public function testSleepWakeupWithAutocommit()
    {
        // __sleep is a magic method, use serialize to trigger it.
        // honestly i have no idea why it even exists. who freezes their db connection?
        $dbh = DB::factory(TestDriver::class);
        $dbh->autoCommit(true);
        $rehydratedObject = unserialize(serialize($dbh));
        $this->assertEquals($dbh, $rehydratedObject);
    }

    public function testToString()
    {
        // honestly testing some of these methods really put a question mark above my sanity.
        $dbh = DB::factory(TestDriver::class);
        $dbh->stubConnect();
        $this->assertEquals(TestDriver::class . ' [connected]', (string) $dbh);
    }

    public function testToStringOnDisconnectedObject()
    {
        // honestly testing some of these methods really put a question mark above my sanity.
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals(TestDriver::class, (string) $dbh);
    }

    public function testQuoteIdentifier()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals('"foo ""bar"" baz"', $dbh->quoteIdentifier('foo "bar" baz'));
    }

    /**
     * @covers Pineapple\DB\Driver\Common::quoteSmart
     * also covers:
     * @covers Pineapple\DB\Driver\Common::quoteBoolean
     * @covers Pineapple\DB\Driver\Common::quoteFloat
     * @covers Pineapple\DB\Driver\Common::escapeSimple
     */
    public function testQuoteSmart()
    {
        $dbh = DB::factory(TestDriver::class);

        // integer
        $this->assertEquals(123, $dbh->quoteSmart(123));
        // float
        $this->assertEquals('\'1.23\'', $dbh->quoteSmart(1.23));
        // boolean, or rather, how to ruin a boolean
        $this->assertEquals(1, $dbh->quoteSmart(true));
        // null
        $this->assertEquals('NULL', $dbh->quoteSmart(null));
        // string
        $this->assertEquals('\'foo\'\'"bar"\'\'baz\'', $dbh->quoteSmart('foo\'"bar"\'baz'));
    }

    public function testProvides()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals('alter', $dbh->provides('limit'));
    }

    public function testSetFetchMode()
    {
        $dbh = DB::factory(TestDriver::class);

        // ugly, but it's this or add methods to result which won't be used
        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('fetchmode');
        $reflectionProp->setAccessible(true);

        // object
        $dbh->setFetchMode(DB::DB_FETCHMODE_OBJECT);
        $this->assertEquals(DB::DB_FETCHMODE_OBJECT, $reflectionProp->getValue($dbh));
        // ordered
        $dbh->setFetchMode(DB::DB_FETCHMODE_ORDERED);
        $this->assertEquals(DB::DB_FETCHMODE_ORDERED, $reflectionProp->getValue($dbh));
        // assoc
        $dbh->setFetchMode(DB::DB_FETCHMODE_ASSOC);
        $this->assertEquals(DB::DB_FETCHMODE_ASSOC, $reflectionProp->getValue($dbh));
    }

    public function testSetFetchModeBadMode()
    {
        $dbh = DB::factory(TestDriver::class);
        $result = $dbh->setFetchMode(-54321);

        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals('DB Error: invalid fetchmode mode', $result->getMessage());
    }

    public function testSetGetOption()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->setOption('result_buffering', 321);
        $this->assertEquals(DB::DB_OK, $result);
        $this->assertEquals(321, $dbh->getOption('result_buffering'));
    }

    public function testSetOptionBadOption()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->setOption('blumfrub', 321);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals('DB Error: unknown option blumfrub', $result->getMessage());
    }

    public function testGetOptionBadOption()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getOption('blumfrub');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals('DB Error: unknown option blumfrub', $result->getMessage());
    }

    public function testGetFetchMode()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals(DB::DB_FETCHMODE_ORDERED, $dbh->getFetchMode());
        $dbh->setFetchMode(DB::DB_FETCHMODE_ASSOC);
        $this->assertEquals(DB::DB_FETCHMODE_ASSOC, $dbh->getFetchMode());
    }

    public function testGetFetchModeObjectClass()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals(\stdClass::class, $dbh->getFetchModeObjectClass());
        $dbh->setFetchMode(DB::DB_FETCHMODE_OBJECT, Row::class);
        $this->assertEquals(Row::class, $dbh->getFetchModeObjectClass());
    }

    public function testPrepareExecuteEmulateQuery()
    {
        $dbh = DB::factory(TestDriver::class);

        $query = '
            SELECT things, stuff
              FROM my_awesome_table
             WHERE good = ?
               AND bad = &
               AND ugly = !
        ';

        $sth = $dbh->prepare($query);

        // buildDetokenisedQuery is a mock to test protected method
        $product = $dbh->buildDetokenisedQuery($sth, [
            'yes',
            __DIR__ . DIRECTORY_SEPARATOR . 'opaquedata.txt',
            'COUNT(dracula)',
        ]);

        $query = preg_replace('/\?/', '\'yes\'', $query);
        $query = preg_replace('/\&/', "'no\n'", $query);
        $query = preg_replace('/!/', 'COUNT(dracula)', $query);

        $this->assertEquals($query, $product);
    }

    public function testPrepareExecuteEmulateQueryWithMismatch()
    {
        $dbh = DB::factory(TestDriver::class);

        $query = '
            SELECT things, stuff
              FROM my_awesome_table
             WHERE good = ?
               AND bad = &
               AND ugly = !
        ';

        $sth = $dbh->prepare($query);

        // buildDetokenisedQuery is a mock to test protected method
        $product = $dbh->buildDetokenisedQuery($sth, ['yes']);

        $this->assertInstanceOf(Error::class, $product);
        $this->assertEquals(DB::DB_ERROR_MISMATCH, $product->getCode());
    }

    public function testAutoPrepare()
    {
        $dbh = DB::factory(TestDriver::class);

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly']);

        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('preparedQueries');
        $reflectionProp->setAccessible(true);

        $preparedQueries = $reflectionProp->getValue($dbh);

        $this->assertEquals('INSERT INTO my_awesome_table (good,bad,ugly) VALUES ( , , )', $preparedQueries[$sth]);
    }

    public function testAutoPrepareWithNoFields()
    {
        $dbh = DB::factory(TestDriver::class);

        $sth = $dbh->autoPrepare('my_awesome_table', []);

        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR_NEED_MORE_DATA, $sth->getCode());
    }

    public function testAutoPrepareUpdate()
    {
        // it's just occurred to me that autoPrepare in update mode is horrific. if your $where clause is empty,
        // say due to a variable being unexpectedly empty, you end up with an update without a where. UTTER HORRORS.
        $dbh = DB::factory(TestDriver::class);
        $dbh->setAcceptConsequencesOfPoorCodingChoices(true);

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly'], DB::DB_AUTOQUERY_UPDATE);

        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('preparedQueries');
        $reflectionProp->setAccessible(true);

        $preparedQueries = $reflectionProp->getValue($dbh);

        $this->assertEquals('UPDATE my_awesome_table SET good =  ,bad =  ,ugly =  ', $preparedQueries[$sth]);
    }

    public function testAutoPrepareUpdateWithWhere()
    {
        // it's just occurred to me that autoPrepare in update mode is horrific. if your $where clause is empty,
        // say due to a variable being unexpectedly empty, you end up with an update without a where. UTTER HORRORS.
        $dbh = DB::factory(TestDriver::class);

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly'], DB::DB_AUTOQUERY_UPDATE, 'id = 123');

        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('preparedQueries');
        $reflectionProp->setAccessible(true);

        $preparedQueries = $reflectionProp->getValue($dbh);

        $this->assertEquals(
            'UPDATE my_awesome_table SET good =  ,bad =  ,ugly =   WHERE id = 123',
            $preparedQueries[$sth]
        );
    }

    public function testAutoPrepareUpdateWithBadMode()
    {
        // it's just occurred to me that autoPrepare in update mode is horrific. if your $where clause is empty,
        // say due to a variable being unexpectedly empty, you end up with an update without a where. UTTER HORRORS.
        $dbh = DB::factory(TestDriver::class);

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly'], -99999);

        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $sth->getCode());
    }

    public function testAutoExecute()
    {
        $dbh = DB::factory(TestDriver::class);

        $sth = $dbh->autoExecute('my_awesome_table', [
            'good' => 'yes',
            'bad' => 'no',
            'ugly' => 'of course',
        ]);

        $this->assertEquals(
            'INSERT INTO my_awesome_table (good,bad,ugly) VALUES (\'yes\',\'no\',\'of course\')',
            $dbh->lastQuery
        );
    }

    public function testAutoExecuteWithTriggeredError()
    {
        $dbh = DB::factory(TestDriver::class);

        $sth = $dbh->autoExecute('my_awesome_table', [
            'good' => 'yes',
            'bad' => 'no',
            'ugly' => 'of course',
        ], DB::DB_AUTOQUERY_UPDATE);

        $this->assertInstanceof(Error::class, $sth);
    }

    public function testExecute()
    {
        $dbh = DB::factory(TestDriver::class);

        // insert gives an OK, not a result set
        $sth = $dbh->prepare('INSERT INTO things SET stuff = 1');
        $this->assertEquals(DB::DB_OK, $dbh->execute($sth));

        // select gives a result set, not a constant
        $sth = $dbh->prepare('SELECT foo FROM bar');
        $this->assertInstanceOf(Result::class, $dbh->execute($sth));

        // a failure at executeEmulateQuery time fails early
        $sth = $dbh->prepare('FAILURE');
        $result = $dbh->execute($sth);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());

        // a failure at simpleQuery time happens as expected
        $sth = $dbh->prepare('ERULIAF');
        $result = $dbh->execute($sth);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testExecuteMultiple()
    {
        $dbh = DB::factory(TestDriver::class);
        // insert gives an OK, not a result set
        $sth = $dbh->prepare('INSERT INTO things SET stuff = ?');
        $this->assertEquals(DB::DB_OK, $dbh->executeMultiple($sth, [['foo'], ['bar'], ['baz']]));
    }

    public function testExecuteMultipleWithFailure()
    {
        $dbh = DB::factory(TestDriver::class);
        // insert gives an OK, not a result set
        $sth = $dbh->prepare('INSERT INTO things SET stuff = 1');
        $result = $dbh->executeMultiple($sth, [['foo'], ['bar'], ['baz']]);
        $this->assertInstanceof(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_MISMATCH, $result->getCode());
    }

    public function testFreePrepared()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->prepare('INSERT INTO things SET stuff = 1');
        $this->assertTrue($dbh->freePrepared($sth));
    }

    public function testFreePreparedHandlesErrors()
    {
        $dbh = DB::factory(TestDriver::class);
        $sth = $dbh->prepare('INSERT INTO things SET stuff = 1');
        $this->assertTrue($dbh->freePrepared($sth));
        $this->assertFalse($dbh->freePrepared($sth));
    }

    public function testModifyQuery()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals('foobar', $dbh->stubModifyQuery('foobar'));
    }

    public function testModifyLimitQuery()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals('foobar', $dbh->stubModifyLimitQuery('foobar', 2, 3));
    }

    public function testQuery()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->query('SELECT things FROM stuff');
        $this->assertEquals(
            [
                'id' => 1,
                'data' => 'test1',
            ],
            $result->fetchRow(DB::DB_FETCHMODE_ASSOC)
        );
    }

    public function testQueryWithBadQuery()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->query('FAILURE');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testQueryWithParameters()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->query('SELECT things FROM stuff WHERE foo = ?', ['bar']);
        $this->assertEquals(
            [
                'id' => 1,
                'data' => 'test1',
            ],
            $result->fetchRow(DB::DB_FETCHMODE_ASSOC)
        );
    }

    public function testQueryWithParametersWithBadParameterCount()
    {
        $dbh = DB::factory(TestDriver::class);

        // @todo there's a crazy bit of code in Common::query where it decides that the query tokenisation routine
        // should not be run if count($data) == 0, which means a query that _shouldn't_ get through to the dbms does
        // actually get through.
        $result = $dbh->query('SELECT things FROM stuff WHERE foo = ?', ['bar', 'bonzo']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_MISMATCH, $result->getCode());
    }

    public function testQueryWithParametersWithBadQuery()
    {
        $dbh = DB::factory(TestDriver::class);

        // @todo there's a crazy bit of code in Common::query where it decides that the query tokenisation routine
        // should not be run if count($data) == 0, which means a query that _shouldn't_ get through to the dbms does
        // actually get through.
        $result = $dbh->query('PREPFAIL', ['bar', 'bonzo']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testLimitQuery()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->limitQuery(
            'SELECT foo FROM bar',
            10,
            5
        );

        $this->assertEquals([
            'id' => 1,
            'data' => 'test1',
        ], $result->fetchRow(DB::DB_FETCHMODE_ASSOC));
    }

    public function testLimitQueryWithSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->limitQuery(
            'FAILURE',
            10,
            5
        );

        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetOne()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getOne('SELECT foo FROM bar');
        $this->assertEquals(1, $result);
    }

    public function testGetOneWithSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getOne('FAILURE');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testGetOneWithNoData()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getOne('EMPTYSEL');
        $this->assertNull($result);
    }

    public function testGetOneWithParameters()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getOne('SELECT foo FROM bar WHERE stuff = ?', ['foo']);
        $this->assertEquals(1, $result);
    }

    public function testGetOneSyntaxErrorWithParameters()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getOne('PREPFAIL SELECT foo FROM bar WHERE stuff = ?', ['foo']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetOneThatFailsWhenDataIsPulled()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getOne('BREAKINGSEL foo FROM bar');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_TRUNCATED, $result->getCode());
    }

    public function testGetRow()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getRow('SELECT foo FROM bar');
        $this->assertEquals([1, 'test1'], $result);
    }

    public function testGetRowWithSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getRow('FAILURE');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testGetRowWithNoData()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getRow('EMPTYSEL');
        $this->assertNull($result);
    }

    public function testGetRowWithParameters()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getRow('SELECT foo FROM bar WHERE stuff = ?', ['foo']);
        $this->assertEquals([1, 'test1'], $result);
    }

    public function testGetRowWithNonArrayParameter()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getRow('SELECT foo FROM bar WHERE stuff = ?', 'foo');
        $this->assertEquals([1, 'test1'], $result);
    }

    public function testGetRowWithWackyParameters()
    {
        $dbh = DB::factory(TestDriver::class);

        // if my eyes aren't deceiving me, it appears that the first decision tree in getRow allows you
        // to transpose the params and fetchmode parameters. to what end i'm not sure.
        $result = $dbh->getRow('SELECT foo FROM bar WHERE foo = ?', null, ['bar']);
        $this->assertEquals([1, 'test1'], $result);
    }

    public function testGetRowWithParametersAndSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getRow('PREPFAIL foo FROM bar WHERE foo = ?', ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetRowWithParametersAndMismatchParameters()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getRow('SELECT foo FROM bar WHERE stuff = ?', ['foo', 'bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_MISMATCH, $result->getCode());
    }

    public function testGetCol()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getCol('SELECT foo FROM bar');
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], $result);
    }

    public function testGetColByName()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getCol('SELECT foo FROM bar', 'data');
        $this->assertEquals([
            'test1',
            'test2',
            'test3',
            'test4',
            'test5',
            'test6',
            'test7',
            'test8',
            'test9',
            'test10',
            'test11',
            'test12',
            'test13',
            'test14',
            'test15',
            'test16',
            'test17',
            'test18',
            'test19',
            'test20',
        ], $result);
    }

    public function testGetColWithParams()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getCol('SELECT foo FROM bar WHERE foo = ?', 0, ['bar']);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], $result);
    }

    public function testGetColWithParamsAndSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getCol('PREPFAIL SELECT foo FROM bar WHERE foo = ?', 0, ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetColWithParamsAndExecutionError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getCol('FAILURE WHERE foo = ?', 0, ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetColWithNonExistentColumn()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getCol('SELECT foo FROM bar', 3);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHFIELD, $result->getCode());
    }

    public function testGetColThatFailsWhenDataIsPulled()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getCol('BREAKINGSEL foo FROM bar');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_TRUNCATED, $result->getCode());
    }

    public function testGetAssoc()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar');
        $this->assertEquals(self::$assocData, $result);
    }

    public function testGetAssocScalarWithGroup()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar', false, [], DB::DB_FETCHMODE_DEFAULT, true);
        $this->assertEquals(self::$orderedArrayData, $result);
    }

    public function testGetAssocWithFailingFetch()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('BREAKINGSEL foo FROM bar');
        $this->assertInstanceOf(Error::class, $result);
    }

    public function testGetAssocWithParameters()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar WHERE foo = ?', false, ['bar']);
        $this->assertEquals(self::$assocData, $result);
    }

    public function testGetAssocWithParametersAndSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('FAILURE WHERE foo = ?', false, ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetAssocWithParametersAndPrepareTimeSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('PREPFAIL WHERE foo = ?', false, ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetAssocWithTooFewColumns()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SINGLECOLSEL WHERE foo = ?', false, ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_TRUNCATED, $result->getCode());
    }

    public function testGetAssocAndForceArray()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar', true);
        $this->assertEquals(self::$orderedArrayData, $result);
    }

    public function testGetAssocAndForceArrayAndGroup()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar', true, [], DB::DB_FETCHMODE_DEFAULT, true);
        $this->assertEquals([
            1 => [['test1']],
            2 => [['test2']],
            3 => [['test3']],
            4 => [['test4']],
            5 => [['test5']],
            6 => [['test6']],
            7 => [['test7']],
            8 => [['test8']],
            9 => [['test9']],
            10 => [['test10']],
            11 => [['test11']],
            12 => [['test12']],
            13 => [['test13']],
            14 => [['test14']],
            15 => [['test15']],
            16 => [['test16']],
            17 => [['test17']],
            18 => [['test18']],
            19 => [['test19']],
            20 => [['test20']]
        ], $result);
    }

    public function testGetAssocAndForceArrayWithAssoc()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar', true, [], DB::DB_FETCHMODE_ASSOC);
        $this->assertEquals([
            1 => ['data' => 'test1'],
            2 => ['data' => 'test2'],
            3 => ['data' => 'test3'],
            4 => ['data' => 'test4'],
            5 => ['data' => 'test5'],
            6 => ['data' => 'test6'],
            7 => ['data' => 'test7'],
            8 => ['data' => 'test8'],
            9 => ['data' => 'test9'],
            10 => ['data' => 'test10'],
            11 => ['data' => 'test11'],
            12 => ['data' => 'test12'],
            13 => ['data' => 'test13'],
            14 => ['data' => 'test14'],
            15 => ['data' => 'test15'],
            16 => ['data' => 'test16'],
            17 => ['data' => 'test17'],
            18 => ['data' => 'test18'],
            19 => ['data' => 'test19'],
            20 => ['data' => 'test20']
        ], $result);
    }

    public function testGetAssocAndForceArrayWithAssocAndGroup()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar', true, [], DB::DB_FETCHMODE_ASSOC, true);
        $this->assertEquals([
            1 => [['data' => 'test1']],
            2 => [['data' => 'test2']],
            3 => [['data' => 'test3']],
            4 => [['data' => 'test4']],
            5 => [['data' => 'test5']],
            6 => [['data' => 'test6']],
            7 => [['data' => 'test7']],
            8 => [['data' => 'test8']],
            9 => [['data' => 'test9']],
            10 => [['data' => 'test10']],
            11 => [['data' => 'test11']],
            12 => [['data' => 'test12']],
            13 => [['data' => 'test13']],
            14 => [['data' => 'test14']],
            15 => [['data' => 'test15']],
            16 => [['data' => 'test16']],
            17 => [['data' => 'test17']],
            18 => [['data' => 'test18']],
            19 => [['data' => 'test19']],
            20 => [['data' => 'test20']]
        ], $result);
    }

    public function testGetAssocAndForceArrayWithObject()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar', true, [], DB::DB_FETCHMODE_OBJECT);
        $this->assertEquals([
            1 => (object) [ 'id' => 1, 'data' => 'test1'],
            2 => (object) [ 'id' => 2, 'data' => 'test2'],
            3 => (object) [ 'id' => 3, 'data' => 'test3'],
            4 => (object) [ 'id' => 4, 'data' => 'test4'],
            5 => (object) [ 'id' => 5, 'data' => 'test5'],
            6 => (object) [ 'id' => 6, 'data' => 'test6'],
            7 => (object) [ 'id' => 7, 'data' => 'test7'],
            8 => (object) [ 'id' => 8, 'data' => 'test8'],
            9 => (object) [ 'id' => 9, 'data' => 'test9'],
            10 => (object) [ 'id' => 10, 'data' => 'test10'],
            11 => (object) [ 'id' => 11, 'data' => 'test11'],
            12 => (object) [ 'id' => 12, 'data' => 'test12'],
            13 => (object) [ 'id' => 13, 'data' => 'test13'],
            14 => (object) [ 'id' => 14, 'data' => 'test14'],
            15 => (object) [ 'id' => 15, 'data' => 'test15'],
            16 => (object) [ 'id' => 16, 'data' => 'test16'],
            17 => (object) [ 'id' => 17, 'data' => 'test17'],
            18 => (object) [ 'id' => 18, 'data' => 'test18'],
            19 => (object) [ 'id' => 19, 'data' => 'test19'],
            20 => (object) [ 'id' => 20, 'data' => 'test20']
        ], $result);
    }

    public function testGetAssocAndForceArrayWithObjectAndGroup()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAssoc('SELECT foo FROM bar', true, [], DB::DB_FETCHMODE_OBJECT, true);
        $this->assertEquals([
            1 => [(object) ['id' => 1, 'data' => 'test1']],
            2 => [(object) ['id' => 2, 'data' => 'test2']],
            3 => [(object) ['id' => 3, 'data' => 'test3']],
            4 => [(object) ['id' => 4, 'data' => 'test4']],
            5 => [(object) ['id' => 5, 'data' => 'test5']],
            6 => [(object) ['id' => 6, 'data' => 'test6']],
            7 => [(object) ['id' => 7, 'data' => 'test7']],
            8 => [(object) ['id' => 8, 'data' => 'test8']],
            9 => [(object) ['id' => 9, 'data' => 'test9']],
            10 => [(object) ['id' => 10, 'data' => 'test10']],
            11 => [(object) ['id' => 11, 'data' => 'test11']],
            12 => [(object) ['id' => 12, 'data' => 'test12']],
            13 => [(object) ['id' => 13, 'data' => 'test13']],
            14 => [(object) ['id' => 14, 'data' => 'test14']],
            15 => [(object) ['id' => 15, 'data' => 'test15']],
            16 => [(object) ['id' => 16, 'data' => 'test16']],
            17 => [(object) ['id' => 17, 'data' => 'test17']],
            18 => [(object) ['id' => 18, 'data' => 'test18']],
            19 => [(object) ['id' => 19, 'data' => 'test19']],
            20 => [(object) ['id' => 20, 'data' => 'test20']]
        ], $result);
    }

    public function testGetAll()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('SELECT foo FROM bar');
        $this->assertEquals(self::$orderedAllData, $result);
    }

    public function testGetAllWithParams()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('SELECT foo FROM bar WHERE foo = ?', ['bar']);
        $this->assertEquals(self::$orderedAllData, $result);
    }

    public function testGetAllWithScalarParam()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('SELECT foo FROM bar WHERE foo = ?', 'bar');
        $this->assertEquals(self::$orderedAllData, $result);
    }

    public function testGetAllWithScalarParamsAndNullModeTransposed()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('SELECT foo FROM bar WHERE foo = ?', null, ['bar']);
        $this->assertEquals(self::$orderedAllData, $result);
    }

    public function testGetAllWithParamsAndSyntaxError()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('PREPFAIL SELECT foo FROM bar WHERE foo = ?', ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testGetAllWithParamsAndFailDuringFetch()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('FAILINGSEL foo FROM bar WHERE foo = ?', ['bar']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testGetAllTransposed()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('SELECT foo FROM bar', [], DB::DB_FETCHMODE_FLIPPED);
        $this->assertEquals([
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
            [
                'test1',
                'test2',
                'test3',
                'test4',
                'test5',
                'test6',
                'test7',
                'test8',
                'test9',
                'test10',
                'test11',
                'test12',
                'test13',
                'test14',
                'test15',
                'test16',
                'test17',
                'test18',
                'test19',
                'test20',
            ],
        ], $result);
    }

    public function testGetAllAssocTransposed()
    {
        $dbh = DB::factory(TestDriver::class);

        $result = $dbh->getAll('SELECT foo FROM bar', [], DB::DB_FETCHMODE_ASSOC | DB::DB_FETCHMODE_FLIPPED);
        $this->assertEquals([
            'id' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
            'data' => [
                'test1',
                'test2',
                'test3',
                'test4',
                'test5',
                'test6',
                'test7',
                'test8',
                'test9',
                'test10',
                'test11',
                'test12',
                'test13',
                'test14',
                'test15',
                'test16',
                'test17',
                'test18',
                'test19',
                'test20',
            ],
        ], $result);
    }

    public function testAutoCommit()
    {
        // this is a stub method intended to fail
        $dbh = DB::factory(TestDriver::class);
        $res = $dbh->autoCommit(true);
        $this->assertInstanceOf(Error::class, $res);
        $this->assertEquals(DB::DB_ERROR_NOT_CAPABLE, $res->getCode());
    }

    public function testCommit()
    {
        // this is a stub method intended to fail
        $dbh = DB::factory(TestDriver::class);
        $res = $dbh->commit();
        $this->assertInstanceOf(Error::class, $res);
        $this->assertEquals(DB::DB_ERROR_NOT_CAPABLE, $res->getCode());
    }

    public function testRollback()
    {
        // this is a stub method intended to fail
        $dbh = DB::factory(TestDriver::class);
        $res = $dbh->rollback();
        $this->assertInstanceOf(Error::class, $res);
        $this->assertEquals(DB::DB_ERROR_NOT_CAPABLE, $res->getCode());
    }

    public function testNumRows()
    {
        // this is a stub method intended to fail
        $dbh = DB::factory(TestDriver::class);
        $result = $dbh->simpleQuery('SELECT foo FROM bar');
        $res = $dbh->stubNumRows($result);
        $this->assertInstanceOf(Error::class, $res);
        $this->assertEquals(DB::DB_ERROR_NOT_CAPABLE, $res->getCode());
    }

    public function testAffectedRows()
    {
        // this is a stub method intended to fail
        $dbh = DB::factory(TestDriver::class);
        $result = $dbh->getAll('SELECT foo FROM bar');
        $res = $dbh->affectedRows($result);
        $this->assertInstanceOf(Error::class, $res);
        $this->assertEquals(DB::DB_ERROR_NOT_CAPABLE, $res->getCode());
    }

    public function testRaiseError()
    {
        $dbh = DB::factory(TestDriver::class);
        $error = $dbh->raiseError();
        $this->assertInstanceOf(Error::class, $error);
        $this->assertEquals(DB::DB_ERROR, $error->getCode());
    }

    public function testRaiseErrorWithNestedError()
    {
        $dbh = DB::factory(TestDriver::class);
        $error1 = $dbh->raiseError(DB::DB_ERROR_TRUNCATED);
        $error2 = $dbh->raiseError($error1);
        $this->assertInstanceOf(Error::class, $error2);
        $this->assertEquals(DB::DB_ERROR_TRUNCATED, $error2->getCode());
    }

    public function testErrorNative()
    {
        // this is a stub method intended to fail
        $dbh = DB::factory(TestDriver::class);
        $result = $dbh->errorNative();
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOT_CAPABLE, $result->getCode());
    }

    public function testErrorCode()
    {
        $dbh = DB::factory(TestDriver::class);

        $this->assertEquals(DB::DB_OK, $dbh->errorCode('00000'));
        $this->assertEquals(DB::DB_ERROR_CONSTRAINT_NOT_NULL, $dbh->errorCode('23502'));
        $this->assertEquals(DB::DB_ERROR, $dbh->errorCode('54321'));
    }

    public function testErrorMessage()
    {
        $dbh = DB::factory(TestDriver::class);

        $this->assertEquals('unknown error', $dbh->errorMessage('blumfrub'));
    }

    public function testTableInfo()
    {
        // this is a stub method intended to fail
        $dbh = DB::factory(TestDriver::class);
        $result = $dbh->simpleQuery('SELECT foo FROM bar');
        $res = $dbh->stubTableInfo($result);
        $this->assertInstanceOf(Error::class, $res);
        $this->assertEquals(DB::DB_ERROR_NOT_CAPABLE, $res->getCode());
    }

    public function testManipQuery()
    {
        $dbh = DB::factory(TestDriver::class);
        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('nextQueryManip');
        $reflectionProp->setAccessible(true);

        $dbh->nextQueryIsManip(true);
        $this->assertEquals(true, $reflectionProp->getValue($dbh));

        $dbh->nextQueryIsManip(false);
        $this->assertEquals(false, $reflectionProp->getValue($dbh));
    }

    public function testCheckManip()
    {
        $dbh = DB::factory(TestDriver::class);

        $this->assertTrue($dbh->stubCheckManip('INSERT INTO foo SET bar = 1'));
        $this->assertFalse($dbh->stubCheckManip('SELECT foo FROM bar'));
    }

    public function testCheckManipWithForcedIsManip()
    {
        $dbh = DB::factory(TestDriver::class);

        $dbh->nextQueryIsManip(true);
        $this->assertTrue($dbh->stubCheckManip('SELECT foo FROM bar'));
    }

    public function testRtrimArrayValues()
    {
        $dbh = DB::factory(TestDriver::class);

        $toTrim = [
            'foo    ',
            'bar  ',
            'baz ',
            ' stoat ',
        ];

        $dbh->stubRtrimArrayValues($toTrim);

        $this->assertEquals([
            'foo',
            'bar',
            'baz',
            ' stoat',
        ], $toTrim);
    }

    public function testConvertNullArrayValuesToEmpty()
    {
        $dbh = DB::factory(TestDriver::class);

        $toConvert = [null, null, null];

        $dbh->stubConvertNullArrayValuesToEmpty($toConvert);

        $this->assertEquals(['', '', ''], $toConvert);
    }

    public function testGetFeature()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertEquals('alter', $dbh->getFeature('limit'));
    }

    public function testGetFeatureWithBadFeature()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->expectException(FeatureException::class);
        $this->assertEquals('alter', $dbh->getFeature('blumfrub'));
    }

    public function testConnected()
    {
        $dbh = DB::factory(TestDriver::class);
        $this->assertFalse($dbh->connected());
        $dbh->stubConnect();
        $this->assertTrue($dbh->connected());
    }

    public function testIsManip()
    {
        $this->assertTrue(TestDriver::isManip('
            INSERT INTO things (foo, bar) VALUES (1, 2)
        '));
        $this->assertTrue(TestDriver::isManip('
            UPDATE things
               SET bar = 2
             WHERE foo = 1
        '));
        $this->assertTrue(TestDriver::isManip('
            DELETE FROM things
                  WHERE foo = 1
        '));
        $this->assertTrue(TestDriver::isManip('
            REPLACE INTO things (foo, bar) VALUES (1, 2)
        '));
        $this->assertTrue(TestDriver::isManip('
            CREATE TABLE things (id INT PRIMARY KEY NOT NULL AUTO_INCREMENT)
        '));
        $this->assertTrue(TestDriver::isManip('
            DROP TABLE things
        '));
        $this->assertTrue(TestDriver::isManip('
            LOAD DATA INFILE "foo.dat"
                        INTO things
        '));
        $this->assertTrue(TestDriver::isManip('
            SELECT foo, bar, baz
              INTO stuff
              FROM things
        '));
        $this->assertTrue(TestDriver::isManip('
            ALTER TABLE whatnot
            DROP COLUMN id
        '));
        $this->assertTrue(TestDriver::isManip('
            GRANT SELECT ON things TO nobody@"%"
        '));
        $this->assertTrue(TestDriver::isManip('
            REVOKE SELECT ON things FROM nobody@"%"
        '));
        $this->assertTrue(TestDriver::isManip('
            LOCK TABLE things
        '));
        $this->assertTrue(TestDriver::isManip('
            UNLOCK TABLE things
        '));
    }

    public function testIsNotManip()
    {
        $this->assertFalse(TestDriver::isManip('
            SELECT lyrics
              FROM track
             WHERE title = "useless"
               AND artist = "depeche mode"
        '));
    }

    public function testLastInsertId()
    {
        $dbh = DB::factory(TestDriver::class);
        $insertId = $dbh->lastInsertId();
        $this->assertInstanceOf(Error::class, $insertId);
        $this->assertEquals(DB::DB_ERROR_UNSUPPORTED, $insertId->getCode());
    }
}
