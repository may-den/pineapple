<?php
namespace Pineapple\Test\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Row;
use Pineapple\DB\Result;
use Pineapple\DB\Error;
use Pineapple\DB\Driver\Common;
use Pineapple\Test\DB\Driver\TestDriver;
use PHPUnit\Framework\TestCase;

// 'Common' is an abstract class so we're going to use our mock TestDriver to stub
// how we access the class and its methods

class CommonTest extends TestCase
{
    public function testConstruct()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertInstanceOf(Common::class, $dbh);
    }

    public function testSleepWakeup()
    {
        // __sleep is a magic method, use serialize to trigger it.
        // honestly i have no idea why it even exists. who freezes their db connection?
        $dbh = DB::connect(TestDriver::class . '://');
        $rehydratedObject = unserialize(serialize($dbh));
        $this->assertEquals($dbh, $rehydratedObject);
    }

    public function testSleepWakeupWithAutocommit()
    {
        // __sleep is a magic method, use serialize to trigger it.
        // honestly i have no idea why it even exists. who freezes their db connection?
        $dbh = DB::connect(TestDriver::class . '://');
        $dbh->autoCommit(true);
        $rehydratedObject = unserialize(serialize($dbh));
        $this->assertEquals($dbh, $rehydratedObject);
    }

    public function testToString()
    {
        // honestly testing some of these methods really put a question mark above my sanity.
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertEquals(TestDriver::class . ': (phptype=test, dbsyntax=test) [connected]', (string) $dbh);
    }

    public function testToStringOnDisconnectedObject()
    {
        // honestly testing some of these methods really put a question mark above my sanity.
        $dbh = DB::connect(TestDriver::class . '://');
        $dbh->disconnect();
        $this->assertEquals(TestDriver::class . ': (phptype=test, dbsyntax=test)', (string) $dbh);
    }

    public function testQuoteIdentifier()
    {
        $dbh = DB::connect(TestDriver::class . '://');
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
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertEquals('alter', $dbh->provides('limit'));
    }

    public function testSetFetchMode()
    {
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');
        $result = $dbh->setFetchMode(-54321);

        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals('DB Error: invalid fetchmode mode', $result->getMessage());
    }

    public function testSetGetOption()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->setOption('result_buffering', 321);
        $this->assertEquals(DB::DB_OK, $result);
        $this->assertEquals(321, $dbh->getOption('result_buffering'));
    }

    public function testSetOptionBadOption()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->setOption('blumfrub', 321);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals('DB Error: unknown option blumfrub', $result->getMessage());
    }

    public function testGetOptionBadOption()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->getOption('blumfrub');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals('DB Error: unknown option blumfrub', $result->getMessage());
    }

    public function testGetFetchMode()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertEquals(DB::DB_FETCHMODE_ORDERED, $dbh->getFetchMode());
        $dbh->setFetchMode(DB::DB_FETCHMODE_ASSOC);
        $this->assertEquals(DB::DB_FETCHMODE_ASSOC, $dbh->getFetchMode());
    }

    public function testGetFetchModeObjectClass()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertEquals(\stdClass::class, $dbh->getFetchModeObjectClass());
        $dbh->setFetchMode(DB::DB_FETCHMODE_OBJECT, Row::class);
        $this->assertEquals(Row::class, $dbh->getFetchModeObjectClass());
    }

    public function testPrepareExecuteEmulateQuery()
    {
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly']);

        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('prepared_queries');
        $reflectionProp->setAccessible(true);

        $preparedQueries = $reflectionProp->getValue($dbh);

        $this->assertEquals('INSERT INTO my_awesome_table (good,bad,ugly) VALUES ( , , )', $preparedQueries[$sth]);
    }

    public function testAutoPrepareWithNoFields()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $sth = $dbh->autoPrepare('my_awesome_table', []);

        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR_NEED_MORE_DATA, $sth->getCode());
    }

    public function testAutoPrepareUpdate()
    {
        // it's just occurred to me that autoPrepare in update mode is horrific. if your $where clause is empty,
        // say due to a variable being unexpectedly empty, you end up with an update without a where. UTTER HORRORS.
        $dbh = DB::connect(TestDriver::class . '://');
        $dbh->setAcceptConsequencesOfPoorCodingChoices(true);

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly'], DB::DB_AUTOQUERY_UPDATE);

        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('prepared_queries');
        $reflectionProp->setAccessible(true);

        $preparedQueries = $reflectionProp->getValue($dbh);

        $this->assertEquals('UPDATE my_awesome_table SET good =  ,bad =  ,ugly =  ', $preparedQueries[$sth]);
    }

    public function testAutoPrepareUpdateWithWhere()
    {
        // it's just occurred to me that autoPrepare in update mode is horrific. if your $where clause is empty,
        // say due to a variable being unexpectedly empty, you end up with an update without a where. UTTER HORRORS.
        $dbh = DB::connect(TestDriver::class . '://');

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly'], DB::DB_AUTOQUERY_UPDATE, 'id = 123');

        $reflectionClass = new \ReflectionClass($dbh);
        $reflectionProp = $reflectionClass->getProperty('prepared_queries');
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
        $dbh = DB::connect(TestDriver::class . '://');

        $sth = $dbh->autoPrepare('my_awesome_table', ['good', 'bad', 'ugly'], -99999);

        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $sth->getCode());
    }

    public function testAutoExecute()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $sth = $dbh->autoExecute('my_awesome_table', [
            'good' => 'yes',
            'bad' => 'no',
            'ugly' => 'of course',
        ]);

        $this->assertEquals(
            'INSERT INTO my_awesome_table (good,bad,ugly) VALUES (\'yes\',\'no\',\'of course\')',
            $dbh->last_query
        );
    }

    public function testAutoExecuteWithTriggeredError()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $sth = $dbh->autoExecute('my_awesome_table', [
            'good' => 'yes',
            'bad' => 'no',
            'ugly' => 'of course',
        ], DB::DB_AUTOQUERY_UPDATE);

        $this->assertInstanceof(Error::class, $sth);
    }

    public function testExecute()
    {
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');
        // insert gives an OK, not a result set
        $sth = $dbh->prepare('INSERT INTO things SET stuff = ?');
        $this->assertEquals(DB::DB_OK, $dbh->executeMultiple($sth, [['foo'], ['bar'], ['baz']]));
    }

    public function testExecuteMultipleWithFailure()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        // insert gives an OK, not a result set
        $sth = $dbh->prepare('INSERT INTO things SET stuff = 1');
        $result = $dbh->executeMultiple($sth, [['foo'], ['bar'], ['baz']]);
        $this->assertInstanceof(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_MISMATCH, $result->getCode());
    }

    public function testFreePrepared()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->prepare('INSERT INTO things SET stuff = 1');
        $this->assertTrue($dbh->freePrepared($sth));
    }

    public function testFreePreparedHandlesErrors()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $sth = $dbh->prepare('INSERT INTO things SET stuff = 1');
        $this->assertTrue($dbh->freePrepared($sth));
        $this->assertFalse($dbh->freePrepared($sth));
    }

    public function testModifyQuery()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertEquals('foobar', $dbh->stubModifyQuery('foobar'));
    }

    public function testModifyLimitQuery()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertEquals('foobar', $dbh->stubModifyLimitQuery('foobar', 2, 3));
    }

    public function testQuery()
    {
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->query('FAILURE');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testQueryWithParameters()
    {
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');

        // @todo there's a crazy bit of code in Common::query where it decides that the query tokenisation routine
        // should not be run if count($data) == 0, which means a query that _shouldn't_ get through to the dbms does
        // actually get through.
        $result = $dbh->query('SELECT things FROM stuff WHERE foo = ?', ['bar', 'bonzo']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_MISMATCH, $result->getCode());
    }

    public function testQueryWithParametersWithBadQuery()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        // @todo there's a crazy bit of code in Common::query where it decides that the query tokenisation routine
        // should not be run if count($data) == 0, which means a query that _shouldn't_ get through to the dbms does
        // actually get through.
        $result = $dbh->query('PREPFAIL', ['bar', 'bonzo']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }

    public function testLimitQuery()
    {
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');

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
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->getOne('SELECT foo FROM bar');
        $this->assertEquals(1, $result);
    }

    public function testGetOneWithSyntaxError()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->getOne('FAILURE');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testGetOneWithNoData()
    {
        $this->markTestIncomplete('test fails, please investigate');
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->getOne('EMPTYSEL');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NOSUCHTABLE, $result->getCode());
    }

    public function testGetOneWithParameters()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->getOne('SELECT foo FROM bar WHERE stuff = ?', ['foo']);
        $this->assertEquals(1, $result);
    }

    public function testGetOneSyntaxErrorWithParameters()
    {
        $dbh = DB::connect(TestDriver::class . '://');

        $result = $dbh->getOne('PREPFAIL SELECT foo FROM bar WHERE stuff = ?', ['foo']);
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_SYNTAX, $result->getCode());
    }
}
