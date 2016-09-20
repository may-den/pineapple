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
    private $dbh = null;
    private $dbalConn = null;

    /**
     * @before
     */
    public function setupDbalInstance()
    {
        $this->dbh = DB::connect('DoctrineDbal://');

        $dbalConfig = new DBALConfiguration();
        $this->dbalConn = DBALDriverManager::getConnection([
            'url' => 'sqlite::memory:',
        ], $dbalConfig);

        $this->dbh->setConnectionHandle($this->dbalConn);
    }

    /**
     * @after
     */
    public function teardownDbalInstance()
    {
        unset($this->dbh);
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
        // this isn't a great test. we can't check something that is unset().
        $this->assertTrue($this->dbh->disconnect());
    }
}
