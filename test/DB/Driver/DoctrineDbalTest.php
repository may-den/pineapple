<?php
namespace Pineapple\Test\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Row;
use Pineapple\DB\Result;
use Pineapple\DB\Error;
use Pineapple\DB\Driver\DoctrineDbal;

use Doctrine\DBAL\DriverManager as DBALDriverManager;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Statement as DBALStatement;

use PHPUnit\Framework\TestCase;

class DoctrineDbalTest extends TestCase
{
    // @var DoctrineDbal
    private $dbh = null;
    private $dbalConn = null;

    private static $setupDb = [
        // general data testing
        'CREATE TABLE dbaltest (a TEXT)',
        'INSERT INTO dbaltest (a) VALUES (\'test1\')',
        'INSERT INTO dbaltest (a) VALUES (\'test2\')',
        'INSERT INTO dbaltest (a) VALUES (\'test3\')',
        'INSERT INTO dbaltest (a) VALUES (\'trimming test    \')',

        // testing key cases
        'CREATE TABLE keycasetest (MixedCaseColumn TEXT)',
        'INSERT INTO keycasetest (MixedCaseColumn) VALUES (\'objet volante non identifie\')',

        // testing conversion of nulls to empty strings
        'CREATE TABLE nullcoalescencetest (a TEXT, b TEXT)',
        'INSERT INTO nullcoalescencetest (a, b) VALUES (\'nebular hypothesis, gimme gimme gimme\', null)',

        // testing transacations (empty table)
        'CREATE TABLE transactiontest (a TEXT)',
    ];

    /**
     * @before
     */
    public function setupDbalInstance()
    {
        $this->dbh = DB::connect('DoctrineDbal://');

        $dbalConfig = new DBALConfiguration();
        $this->dbalConn = DBALDriverManager::getConnection([
            'url' => 'sqlite:///:memory:',
        ], $dbalConfig);

        foreach (self::$setupDb as $sql) {
            $this->dbalConn->query($sql);
        }

        $this->dbh->setConnectionHandle($this->dbalConn);
    }

    /**
     * @after
     */
    public function teardownDbalInstance()
    {
        unset($this->dbh);
        unset($this->dbalConn);
    }

    public function testConstruct()
    {
        $this->assertInstanceOf(DoctrineDbal::class, $this->dbh);
    }

    public function testSetConnectionHandle()
    {
        // use reflection to ensure the connection handle is a dbal instance
        $reflectionClass = new \ReflectionClass($this->dbh);
        $reflectionProp = $reflectionClass->getProperty('connection');
        $reflectionProp->setAccessible(true);

        $this->assertInstanceOf(DBALConnection::class, $reflectionProp->getValue($this->dbh));
    }

    public function testSetConnectionHandleWithSyntaxAndDatabase()
    {
        // use reflection to ensure the connection handle is a dbal instance
        $this->dbh->setConnectionHandle($this->dbalConn, DB::parseDSN('sqlite://foo@bar/mydb'));

        $reflectionClass = new \ReflectionClass($this->dbh);
        $syntaxProp = $reflectionClass->getProperty('dbsyntax');
        $syntaxProp->setAccessible(true);
        $databaseProp = $reflectionClass->getProperty('db');
        $databaseProp->setAccessible(true);

        $this->assertEquals('sqlite', $syntaxProp->getValue($this->dbh));
        $this->assertEquals('mydb', $databaseProp->getValue($this->dbh));
    }

    public function testDisconnect()
    {
        // this isn't a great test. we can't check something that is unset() using reflection.
        $this->assertTrue($this->dbh->disconnect());
    }

    public function testSimpleQuery()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');
        $result = new Result($this->dbh, $sth);
        $this->assertEquals(['test1'], $result->fetchRow());
    }

    public function testSimpleQueryWithNoConnection()
    {
        $this->dbh->disconnect();
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');
        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR_NODBSELECTED, $sth->getCode());
    }

    public function testSimpleQueryWithInsert()
    {
        $result = $this->dbh->simpleQuery('INSERT INTO dbaltest (a) VALUES (\'onyx\')');
        $this->assertEquals(DB::DB_OK, $result);
    }

    public function testSimpleQueryWithInsertAndWithoutAutocommit()
    {
        $this->dbh->autoCommit(false);
        $result = $this->dbh->simpleQuery('INSERT INTO dbaltest (a) VALUES (\'onyx\')');
        $this->assertEquals(DB::DB_OK, $result);
    }

    public function testSimpleQueryWithSyntaxError()
    {
        $sth = $this->dbh->simpleQuery('BLUMFRUB');
        $this->assertInstanceOf(Error::class, $sth);
        $this->assertEquals(DB::DB_ERROR, $sth->getCode());
    }

    public function testNextResult()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');
        // not all drivers support stacked queries. detecting which is a fine art.
        // in honesty, it's more trouble than it's worth in terms of this legacy library.
        $this->assertFalse($this->dbh->nextResult($sth));
    }

    public function testFetchInto()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');
        $data = [];
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_DEFAULT);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['test1'], $data);
    }

    public function testFetchIntoAssocMode()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');
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
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest LIMIT 1');
        $this->assertInstanceOf(DBALStatement::class, $sth);

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
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest WHERE a LIKE \'trimming%\'');
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
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');

        $this->assertInstanceOf(DBALStatement::class, $sth);
        $this->assertTrue($this->dbh->freeResult($sth));
        $this->assertInstanceOf(DBALStatement::class, $sth);
    }

    public function testFreeResultAlreadyFreed()
    {
        $sth = null;
        $this->assertFalse($this->dbh->freeResult($sth));
    }

    public function testNumCols()
    {
        $sth = $this->dbh->simpleQuery('SELECT * FROM nullcoalescencetest');
        $this->assertEquals(2, $this->dbh->numCols($sth));
    }

    public function testNumColsWithNoColumns()
    {
        $sth = $this->dbh->simpleQuery('REINDEX dbaltest');
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
        $this->dbh->query('INSERT INTO transactiontest (a) VALUES (\'the nurse who loved me\'),(\'solaris\')');
        $this->assertEquals(2, $this->dbh->affectedRows());
    }

    public function testAffectedRowsWithNoLastStatement()
    {
        // use our own connection so we don't have rowCount from test fixture setup
        $myDbh = DB::connect('DoctrineDbal://');

        $dbalConfig = new DBALConfiguration();
        $myDbalConn = DBALDriverManager::getConnection([
            'url' => 'sqlite:///:memory:',
        ], $dbalConfig);

        $myDbh->setConnectionHandle($myDbalConn);

        $result = $myDbh->affectedRows();

        $this->assertInstanceOf(Error::class, $result);
        $this->assertEquals(DB::DB_ERROR, $result->getCode());
    }

    public function testAffectedRowsOnNonManipulativeQuery()
    {
        $this->dbh->query('SELECT * FROM dbaltest');
        $this->assertEquals(0, $this->dbh->affectedRows());
    }

    public function testQuoteIdentifier()
    {
        $this->assertEquals('`foo``bar`', $this->dbh->quoteIdentifier('foo`bar'));
    }
}
