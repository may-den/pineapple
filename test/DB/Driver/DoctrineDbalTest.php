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

use PHPUnit\Framework\TestCase;

class DoctrineDbalTest extends TestCase
{
    // @var DoctrineDbal
    private $dbh = null;
    private $dbalConn = null;

    private static $setupDb = [
        'CREATE TABLE dbaltest (a TEXT)',
        'INSERT INTO dbaltest (a) VALUES (\'test1\')',
        'INSERT INTO dbaltest (a) VALUES (\'test2\')',
        'INSERT INTO dbaltest (a) VALUES (\'test3\')',
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
        $data = [];
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_DEFAULT);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['test1'], $data);
    }

    public function testFetchIntoAssocMode()
    {
        $data = [];
        $sth = $this->dbh->simpleQuery('SELECT * FROM dbaltest');
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_ASSOC);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['a' => 'test1'], $data);
    }

    public function testFetchIntoAssocModeWithNoData()
    {
        $this->markTestIncomplete('test not complete');
        $data = [];
        $sth = $this->dbh->simpleQuery('INSERT INTO dbaltest (\'a\') VALUES (\'jadzia\'))');
        $result = $this->dbh->fetchInto($sth, $data, DB::DB_FETCHMODE_ASSOC);

        // a success code
        $this->assertEquals(DB::DB_OK, $result);
        // and a row of data
        $this->assertEquals(['a' => 'test1'], $data);
    }
}
