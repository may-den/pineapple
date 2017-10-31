<?php
namespace Pineapple\Test\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Row;
use Pineapple\DB\Result;
use Pineapple\DB\Error;
use Pineapple\DB\Driver\PdoDriver;
use Pineapple\Test\DB\Driver\TestDriver;
use Pineapple\DB\Exception\StatementException;
use Pineapple\DB\Exception\DriverException;

use Prophecy\Prophet;
use PDO;
use PDOStatement;
use PDOException;

use PHPUnit\Framework\TestCase;

class PdoDriverTest extends TestCase
{
    // @var PdoDriver Our Pineapple DB connection
    private $dbh = null;

    // @var PDO The PDO connection object
    private $pdoConn = null;

    // @var array
    private static $setupDb = [
        // general data testing
        'CREATE TABLE pdotest (a TEXT UNIQUE)',
        'INSERT INTO pdotest (a) VALUES (\'test1\')',
        'INSERT INTO pdotest (a) VALUES (\'test2\')',
        'INSERT INTO pdotest (a) VALUES (\'test3\')',
        'INSERT INTO pdotest (a) VALUES (\'trimming test    \')',

        // testing key cases
        'CREATE TABLE keycasetest (MixedCaseColumn TEXT)',
        'INSERT INTO keycasetest (MixedCaseColumn) VALUES (\'objet volante non identifie\')',

        // testing conversion of nulls to empty strings
        'CREATE TABLE nullcoalescencetest (a TEXT, b TEXT)',
        'INSERT INTO nullcoalescencetest (a, b) VALUES (\'nebular hypothesis, gimme gimme gimme\', null)',

        // testing transacations (empty table)
        'CREATE TABLE transactiontest (a TEXT)',

        // empty table
        'CREATE TABLE emptytable (a TEXT)',
    ];

    /**
     * @before
     */
    public function setupPdoInstance()
    {
        $this->dbh = DB::factory(PdoDriver::class);
        $this->pdoConn = new PDO('sqlite::memory:');

        foreach (self::$setupDb as $sql) {
            $this->pdoConn->query($sql);
        }

        $this->dbh->setConnectionHandle($this->pdoConn);
    }

    /**
     * @after
     */
    public function teardownPdoInstance()
    {
        unset($this->dbh);
        unset($this->pdoConn);
    }

    public function testConstruct()
    {
        $this->assertInstanceOf(PdoDriver::class, $this->dbh);
    }

    public function testSetConnectionHandle()
    {
        // use reflection to ensure the connection handle is a pdo instance
        $reflectionClass = new \ReflectionClass($this->dbh);
        $reflectionProp = $reflectionClass->getProperty('connection');
        $reflectionProp->setAccessible(true);

        $this->assertInstanceOf(PDO::class, $reflectionProp->getValue($this->dbh));
        $this->assertTrue($this->dbh->connected());
    }

    public function testDisconnect()
    {
        $this->assertTrue($this->dbh->disconnect());
        $this->assertFalse($this->dbh->connected());
    }

    public function testSimpleQuery()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest');
        $result = new Result($this->dbh, $sth);
        $this->assertEquals(['test1'], $result->fetchRow());
    }

    public function testSimpleQueryWithNoConnection()
    {
        $this->dbh->disconnect();
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest');
        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR_NODBSELECTED, $sth->getCode());
    }

    public function testSimpleQueryWithInsert()
    {
        $result = $this->dbh->simpleQuery('INSERT INTO pdotest (a) VALUES (\'onyx\')');
        $this->assertEquals(DB::DB_OK, $result);
    }

    public function testSimpleQueryWithInsertAndWithoutAutocommit()
    {
        $this->dbh->autoCommit(false);
        $result = $this->dbh->simpleQuery('INSERT INTO pdotest (a) VALUES (\'onyx\')');
        $this->assertEquals(DB::DB_OK, $result);
    }

    public function testSimpleQueryWithSyntaxError()
    {
        $sth = $this->dbh->simpleQuery('BLUMFRUB');
        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR, $sth->getCode());
    }

    public function testSimpleQueryWithSyntaxErrorExceptionMode()
    {
        $this->pdoConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sth = $this->dbh->simpleQuery('BLUMFRUB');
        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR, $sth->getCode());
    }

    public function testSimpleQueryWithExecuteTimeFailure()
    {
        $sth = $this->dbh->simpleQuery('INSERT INTO pdotest (a) VALUES (\'test1\')');
        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR, $sth->getCode());
    }

    public function testSimpleQueryWithExecuteTimeFailureExceptionMode()
    {
        $this->pdoConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sth = $this->dbh->simpleQuery('INSERT INTO pdotest (a) VALUES (\'test1\')');
        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR, $sth->getCode());
    }

    public function testNextResult()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest');
        // not all drivers support stacked queries. detecting which is a fine art.
        // in honesty, it's more trouble than it's worth in terms of this legacy library.
        $this->assertFalse($this->dbh->nextResult($sth));
    }

    public function testFetchInto()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_DEFAULT);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['test1'], $data);
    }

    public function testFetchIntoTriggeringInvalidStatement()
    {
        $testDbh = DB::factory(TestDriver::class);
        $testSth = $testDbh->simpleQuery('SELECT foo FROM bar');
        $data = [];
        $this->expectException(DriverException::class);
        $result = $this->dbh->fetchInto($testSth, $data, DB::DB_FETCHMODE_DEFAULT);
    }

    public function testFetchIntoAssocMode()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_ASSOC);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['a' => 'test1'], $data);
    }

    public function testFetchIntoAssocModeWithNoData()
    {
        // query and get a raw statement from the driver
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest LIMIT 1');
        $this->assertInstanceOf(PDOStatement::class, $sth->getStatement());

        // first fetch should give us a valid row
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_ASSOC);
        $this->assertEquals(DB::DB_OK, $result);
        $this->assertEquals(['a' => 'test1'], $data);

        // second fetch should produce false from the driver, and fetchInto return nul
        $data = []; // the actual return from fetchInto for $data is unpredictable
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_ASSOC);
        $this->assertNull($result);
    }

    public function testFetchIntoAssocModeWithKeyCaseSquashing()
    {
        $this->dbh->setOption('portability', DB::DB_PORTABILITY_LOWERCASE);
        $sth = $this->dbh->simpleQuery('SELECT * FROM keycasetest');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_ASSOC);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['mixedcasecolumn' => 'objet volante non identifie'], $data);
    }

    public function testFetchIntoWithTrimming()
    {
        $this->dbh->setOption('portability', DB::DB_PORTABILITY_RTRIM);
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest WHERE a LIKE \'trimming%\'');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_DEFAULT);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['trimming test'], $data);
    }

    public function testFetchIntoWithNullCoalescence()
    {
        // before - this should come out with a null in the array
        $sth = $this->dbh->simpleQuery('SELECT b FROM nullcoalescencetest');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_DEFAULT);

        $this->assertEquals(DB::DB_OK, $result);
        $this->assertNull($data[0]);

        // after - this should come out with nulls converted into empty strings
        $this->dbh->setOption('portability', DB::DB_PORTABILITY_NULL_TO_EMPTY);
        $sth = $this->dbh->simpleQuery('SELECT b FROM nullcoalescencetest');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_DEFAULT);

        $this->assertEquals(DB::DB_OK, $result);
        $this->assertEmpty($data[0]);
    }

    public function testFreeResult()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM pdotest');

        $this->assertInstanceOf(PDOStatement::class, $sth->getStatement());
        $this->assertTrue($this->dbh->freeResult($sth));
        $this->expectException(StatementException::class);
        $this->expectExceptionCode(StatementException::NO_STATEMENT);
        $sth->getStatement();
    }

    public function testNumCols()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM nullcoalescencetest');
        $this->assertEquals(2, $this->dbh->numCols($sth));
    }

    public function testNumColsWithNoColumns()
    {
        $sth = $this->dbh->simpleQuery('REINDEX pdotest');
        $this->assertInstanceOf(Error::class, $this->dbh->numCols($sth));
    }

    public function testAutoCommit()
    {
        // don't like reflection, but want to test this important method
        $reflectionClass = new \ReflectionClass($this->dbh);
        $reflectionProp = $reflectionClass->getProperty('autocommit');
        $reflectionProp->setAccessible(true);

        // on by default
        $this->assertTrue($reflectionProp->getValue($this->dbh));

        // turn it off by default value
        $this->dbh->autoCommit();
        $this->assertFalse($reflectionProp->getValue($this->dbh));

        // turn it back on so we can test it with implicit turn off
        $this->dbh->autoCommit(true);
        $this->assertTrue($reflectionProp->getValue($this->dbh));

        // and lastly test implicit turn off
        $this->dbh->autoCommit(false);
        $this->assertFalse($reflectionProp->getValue($this->dbh));
    }

    public function testAutoCommitStrictModeWithActiveTransaction()
    {
        // don't like reflection, but want to test this important method
        $this->dbh->autoCommit(false);
        $this->dbh->query('INSERT INTO pdotest (a) VALUES (\'autcomm on\')');
        $result = $this->dbh->autoCommit(true);

        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_ACTIVE_TRANSACTIONS, $result->getCode());
    }

    public function testCommitWithNoActiveTransaction()
    {
        $this->assertEquals(DB::DB_OK, $this->dbh->commit());
    }

    public function testCommitWithActiveTransaction()
    {
        $this->dbh->autoCommit(false);
        $this->dbh->query('INSERT INTO transactiontest (a) VALUES (\'the nurse who loved me\')');
        $this->assertEquals(DB::DB_OK, $this->dbh->commit());
    }

    public function testCommitWithDisconnection()
    {
        $this->dbh->autoCommit(false);
        $this->dbh->query('INSERT INTO transactiontest (a) VALUES (\'the nurse who loved me\')');
        $this->dbh->disconnect();

        $result = $this->dbh->commit();
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NODBSELECTED, $result->getCode());
    }

    public function testRollback()
    {
        $this->dbh->autoCommit(false);
        $this->dbh->query('INSERT INTO transactiontest (a) VALUES (\'the nurse who loved me\')');
        $this->dbh->rollback();

        $sth = $this->dbh->simpleQuery('SELECT * FROM transactiontest');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_ASSOC);

        $this->assertNull($result);
        $this->assertFalse($data);
    }

    public function testRollbackWithDisconnection()
    {
        $this->dbh->autoCommit(false);
        $this->dbh->query('INSERT INTO transactiontest (a) VALUES (\'the nurse who loved me\')');
        $this->dbh->disconnect();

        $result = $this->dbh->rollback();
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_NODBSELECTED, $result->getCode());
    }

    public function testAffectedRows()
    {
        $this->dbh->query('INSERT INTO transactiontest (a) VALUES (\'the nurse who loved me\')');
        $this->dbh->query('INSERT INTO transactiontest (a) VALUES (\'solaris\')');
        $this->dbh->query('UPDATE transactiontest SET a = \'the goonies\'');
        $this->assertEquals(2, $this->dbh->affectedRows());
    }

    public function testAffectedRowsWithNoLastStatement()
    {
        // use our own connection so we don't have rowCount from test fixture setup
        $myDbh = DB::factory(PdoDriver::class);
        $myPdoConn = new PDO('sqlite::memory:');
        $myDbh->setConnectionHandle($myPdoConn);

        $result = $myDbh->affectedRows();

        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR, $result->getCode());
    }

    public function testAffectedRowsOnNonManipulativeQuery()
    {
        $this->dbh->query('SELECT * FROM pdotest');
        $this->assertEquals(0, $this->dbh->affectedRows());
    }

    public function testQuoteIdentifier()
    {
        $this->assertEquals('`foo``bar`', $this->dbh->quoteIdentifier('foo`bar'));
    }

    public function testEscapeSimple()
    {
        $this->assertEquals(
            'foof`foof',
            $this->dbh->escapeSimple('foof`foof')
        );

        $this->assertEquals(
            '"foof"',
            $this->dbh->escapeSimple('"foof"')
        );

        $this->assertEquals(
            '\'\'foof\'\'',
            $this->dbh->escapeSimple('\'foof\'')
        );

        $this->assertEquals(
            '1234',
            $this->dbh->escapeSimple('1234')
        );
    }

    public function testModifyLimitQuery()
    {
        $result = $this->dbh->limitQuery(
            'SELECT a FROM pdotest',
            1,
            1
        );
        $this->assertInstanceOf(Result::class, $result);

        $this->assertEquals(['test2'], $row = $result->fetchRow());
        $this->assertNull($row = $result->fetchRow());
    }

    public function testErrorNative()
    {
        $this->dbh->query('BORKAGE');
        // this is, obviously, an sqlite error code
        $this->assertEquals('HY000', $this->dbh->errorNative());
    }

    public function testTableInfo()
    {
        $result = $this->dbh->query('SELECT * FROM pdotest');
        $tableInfo = $this->dbh->tableInfo($result);

        $this->assertEquals(1, count($tableInfo));

        unset($tableInfo[0]['len']);
        $this->assertEquals([
            [
                'table' => 'pdotest',
                'name' => 'a',
                'type' => 'string',
                'flags' => '',
            ]
        ], $tableInfo);
    }

    public function testTableInfoWithStringOnDisconnectedConnection()
    {
        $this->dbh->disconnect();
        $tableInfo = $this->dbh->tableInfo('pdotest');

        $this->assertInstanceOf(Error::class, $tableInfo);
    }

    public function testTableInfoWithBadValue()
    {
        $this->dbh->disconnect();
        $tableInfo = $this->dbh->tableInfo(false);

        $this->assertInstanceOf(Error::class, $tableInfo);
        $this->assertEquals(DB::DB_ERROR, $tableInfo->getCode());
    }

    public function testTableInfoWithLowerCase()
    {
        $this->dbh->setOption('portability', DB::DB_PORTABILITY_LOWERCASE);
        $result = $this->dbh->query('SELECT * FROM keycasetest');
        $tableInfo = $this->dbh->tableInfo($result);

        $this->assertEquals(1, count($tableInfo));

        unset($tableInfo[0]['len']);
        $this->assertEquals([
            [
                'table' => 'keycasetest',
                'name' => 'mixedcasecolumn',
                'type' => 'string',
                'flags' => '',
            ]
        ], $tableInfo);
    }

    public function testTableInfoWithModeOrder()
    {
        $result = $this->dbh->query('SELECT * FROM pdotest');
        $tableInfo = $this->dbh->tableInfo($result, DB::DB_TABLEINFO_ORDER);

        $this->assertEquals(3, count($tableInfo));

        unset($tableInfo[0]['len']);
        $this->assertEquals([
            [
                'table' => 'pdotest',
                'name' => 'a',
                'type' => 'string',
                'flags' => '',
            ],
            'num_fields' => 1,
            'order' => ['a' => 0],
        ], $tableInfo);
    }

    public function testTableInfoWithModeOrderTable()
    {
        $result = $this->dbh->query('SELECT * FROM pdotest');
        $tableInfo = $this->dbh->tableInfo($result, DB::DB_TABLEINFO_ORDERTABLE);

        $this->assertEquals(3, count($tableInfo));

        unset($tableInfo[0]['len']);
        $this->assertEquals([
            [
                'table' => 'pdotest',
                'name' => 'a',
                'type' => 'string',
                'flags' => '',
            ],
            'num_fields' => 1,
            'ordertable' => [
                'pdotest' => [
                    'a' => 0,
                ],
            ],
        ], $tableInfo);
    }

    public function testLastInsertId()
    {
        $this->dbh->query('INSERT INTO pdotest (a) VALUES (\'lama farmer\')');
        $this->assertEquals(5, $this->dbh->lastInsertId());
    }

    public function testGetOne()
    {
        $data = $this->dbh->getOne('SELECT * FROM pdotest');
        $this->assertEquals('test1', $data);
    }

    public function testGetOneWithNoData()
    {
        // PLEASE NOTE
        // AT SOME POINT IN THE FUTURE THIS BEHAVIOUR MAY CHANGE AND THIS TEST MAY FAIL AND REQUIRE REWORKING
        // YOU HAVE BEEN WARNED
        // RIGHT NOW THIS TESTS THE EXISTING FUNCTIONALITY
        $data = $this->dbh->getOne('SELECT * FROM emptytable');
        $this->assertNull($data);
    }

    /** @test */
    public function itCanChangeDatabase()
    {
        $prophet = new Prophet;

        // stub the statement class
        $pStubStatement = $prophet->prophesize(PDOStatement::class);
        $pStubStatement->execute()
            ->shouldBeCalled();

        // stub the pdo connection
        $pStubPdo = $prophet->prophesize(PDO::class);
        $pStubPdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)
            ->shouldBeCalled();
        $pStubPdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql')
            ->shouldBeCalled();
        $pStubPdo->prepare('USE `myTestValue`', [])
            ->willReturn($pStubStatement->reveal())
            ->shouldBeCalled();

        // build the db object and change database
        $db = DB::factory(PdoDriver::class);
        $db->setConnectionHandle($pStubPdo->reveal());

        // this is what we came here to test
        $db->changeDatabase('myTestValue');

        // check that everything that should have been called has been so
        $prophet->checkPredictions();
    }

    /** @test */
    public function itFailsWhenChangingDatabaseOnAnUnsupportedPlatform()
    {
        $prophet = new Prophet;

        // stub the pdo connection
        $pStubPdo = $prophet->prophesize(PDO::class);
        $pStubPdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            ->willReturn('unsupported')
            ->shouldBeCalled();

        // build the db object and change database
        $db = DB::factory(PdoDriver::class);
        $db->setConnectionHandle($pStubPdo->reveal());

        // this is what we came here to test
        $result = $db->changeDatabase('myTestValue');
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_UNSUPPORTED, $result->getCode());

        // check that everything that should have been called has been so
        $prophet->checkPredictions();
    }

    /** @test */
    public function itFailsWhenLastInsertIdThrowsAnException()
    {
        $prophet = new Prophet;

        // stub the pdo connection
        $pStubPdo = $prophet->prophesize(PDO::class);
        $pStubPdo->lastInsertId(null)
            ->willThrow(new PDOException('Not supported by platform'))
            ->shouldBeCalled();

        // build the db object and change database
        $db = DB::factory(PdoDriver::class);
        $db->setConnectionHandle($pStubPdo->reveal());

        // this is what we came here to test
        $result = $db->lastInsertId();
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR, $result->getCode());

        // check that everything that should have been called has been so
        $prophet->checkPredictions();
    }

    /** @test */
    public function itFailsWhenLastInsertIdFails()
    {
        $prophet = new Prophet;

        // stub the pdo connection
        $pStubPdo = $prophet->prophesize(PDO::class);
        $pStubPdo->lastInsertId(null)
            ->willReturn(false)
            ->shouldBeCalled();

        $pStubPdo->errorCode()
            ->willReturn('01P01')
            ->shouldBeCalled();

        // build the db object and change database
        $db = DB::factory(PdoDriver::class);
        $db->setConnectionHandle($pStubPdo->reveal());

        // this is what we came here to test
        $result = $db->lastInsertId();
        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR_UNSUPPORTED, $result->getCode());

        // check that everything that should have been called has been so
        $prophet->checkPredictions();
    }
}
