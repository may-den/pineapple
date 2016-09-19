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
        $this->assertFalse($dbh->getOption('persistent'));
    }

    public function testFactoryWithArrayOptions()
    {
        $dbh = DB::factory(TestDriver::class, ['debug' => 'q who?']);
        $this->assertInstanceOf(TestDriver::class, $dbh);
        $this->assertEquals('q who?', $dbh->getOption('debug'));
    }

    public function testFactoryWithLegacyOption()
    {
        $dbh = DB::factory(TestDriver::class, true);
        $this->assertInstanceOf(TestDriver::class, $dbh);
        $this->assertTrue($dbh->getOption('persistent'));
    }

    public function testFactoryWithBadDriver()
    {
        $dbh = DB::factory('murp');
        $this->assertInstanceOf(Error::class, $dbh);
    }

    public function testConnect()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertInstanceOf(TestDriver::class, $dbh);
    }

    public function testConnectWithDsnArray()
    {
        $dsn = ['phptype' => TestDriver::class];
        $dbh = DB::connect($dsn);
        $this->assertInstanceOf(TestDriver::class, $dbh);
    }

    public function testConnectWithOptions()
    {
        $dbh = DB::connect(TestDriver::class . '://', ['debug' => 'q who?']);
        $this->assertInstanceOf(TestDriver::class, $dbh);
        $this->assertEquals('q who?', $dbh->getOption('debug'));
    }

    public function testConnectWithLegacyOption()
    {
        $dbh = DB::connect(TestDriver::class . '://', true);
        $this->assertInstanceOf(TestDriver::class, $dbh);
        $this->assertTrue($dbh->getOption('persistent'));
    }

    public function testConnectWithBadDriver()
    {
        $dbh = DB::connect('meep://');
        $this->assertInstanceOf(Error::class, $dbh);
    }

    public function testConnectWithFailingDriver()
    {
        $dbh = DB::connect(TestDriver::class . '://', ['debug' => 'please fail']); // magic value
        $this->assertInstanceOf(Error::class, $dbh);
    }

    public function testConnectWithDsnArrayAndFailingDriver()
    {
        $dsn = ['phptype' => TestDriver::class];
        $dbh = DB::connect($dsn, ['debug' => 'please fail']);
        $this->assertInstanceOf(Error::class, $dbh);
    }

    public function testIsConnection()
    {
        $dbh = DB::connect(TestDriver::class . '://');
        $this->assertTrue(DB::isConnection($dbh));
    }

    public function testIsNotConnection()
    {
        $this->assertFalse(DB::isConnection(new \stdClass));
    }

    public function testIsManip()
    {
        $this->assertTrue(DB::isManip('
            INSERT INTO things (foo, bar) VALUES (1, 2)
        '));
        $this->assertTrue(DB::isManip('
            UPDATE things
               SET bar = 2
             WHERE foo = 1
        '));
        $this->assertTrue(DB::isManip('
            DELETE FROM things
                  WHERE foo = 1
        '));
        $this->assertTrue(DB::isManip('
            REPLACE INTO things (foo, bar) VALUES (1, 2)
        '));
        $this->assertTrue(DB::isManip('
            CREATE TABLE things (id INT PRIMARY KEY NOT NULL AUTO_INCREMENT)
        '));
        $this->assertTrue(DB::isManip('
            DROP TABLE things
        '));
        $this->assertTrue(DB::isManip('
            LOAD DATA INFILE "foo.dat"
                        INTO things
        '));
        $this->assertTrue(DB::isManip('
            SELECT foo, bar, baz
              INTO stuff
              FROM things
        '));
        $this->assertTrue(DB::isManip('
            ALTER TABLE whatnot
            DROP COLUMN id
        '));
        $this->assertTrue(DB::isManip('
            GRANT SELECT ON things TO nobody@"%"
        '));
        $this->assertTrue(DB::isManip('
            REVOKE SELECT ON things FROM nobody@"%"
        '));
        $this->assertTrue(DB::isManip('
            LOCK TABLE things
        '));
        $this->assertTrue(DB::isManip('
            UNLOCK TABLE things
        '));
    }

    public function testIsNotManip()
    {
        $this->assertFalse(DB::isManip('
            SELECT lyrics
              FROM track
             WHERE title = "useless"
               AND artist = "depeche mode"
        '));
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

    public function testParseDsn()
    {
        $dsn = 'proto://';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithHostname()
    {
        $dsn = 'proto://hostname';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => 'hostname',
            'port' => false,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithHostnameAndPort()
    {
        $dsn = 'proto://hostname:1234';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => 'hostname',
            'port' => 1234,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithHostnameProtocolAndPort()
    {
        // this tests the _theory_ of a protocol, but the code only handles proto+hostname for
        // tcp connections, so this is a moot combination really.
        $dsn = 'proto://tcp+hostname:1234';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => 'hostname',
            'port' => 1234,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithUsername()
    {
        $dsn = 'proto://test123@hostname';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => 'test123',
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => 'hostname',
            'port' => false,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithUsernameAndPassword()
    {
        $dsn = 'proto://test123:pass321@hostname';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => 'test123',
            'password' => 'pass321',
            'protocol' => 'tcp',
            'hostspec' => 'hostname',
            'port' => false,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithDbName()
    {
        $dsn = 'proto:///dbname';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => 'dbname',
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithSocket()
    {
        $dsn = 'proto://unix+%2fvar%2frun%2fmysql.sock';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'unix',
            'hostspec' => false,
            'port' => false,
            'socket' => '/var/run/mysql.sock',
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithDbNameAndParameter()
    {
        $dsn = 'proto:///dbname?utf8=true';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => 'dbname',
            'utf8' => 'true',
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithDbNameAndParameters()
    {
        $dsn = 'proto:///dbname?utf8=true&sandwich=cheese';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => 'dbname',
            'utf8' => 'true',
            'sandwich' => 'cheese',
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnNonUrl()
    {
        $dsn = 'proto';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => false,
            'hostspec' => false,
            'port' => false,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnNonUrlWithSyntax()
    {
        $dsn = 'proto(blumfrub)';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'blumfrub',
            'username' => false,
            'password' => false,
            'protocol' => false,
            'hostspec' => false,
            'port' => false,
            'socket' => false,
            'database' => false,
        ], DB::parseDSN($dsn));
    }

    public function testParseDsnWithProtoAndDbName()
    {
        $dsn = 'proto://lineproto(opts)/dbname';
        $this->assertEquals([
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'lineproto',
            'hostspec' => false,
            'port' => false,
            'socket' => false,
            'database' => 'dbname',
        ], DB::parseDSN($dsn));
    }

    public function testGetDsnString()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => false,
        ];
        $this->assertEquals('proto(proto)://:@tcp/', DB::getDSNString($parsedDsn, false));
    }

    public function testGetDsnStringWithUsernameAndPassword()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => 'user123',
            'password' => 'pass321',
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => false,
        ];
        $this->assertEquals('proto(proto)://user123:pass321@tcp/', DB::getDSNString($parsedDsn, false));
    }

    public function testGetDsnStringWithSocket()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => 'user123',
            'password' => 'pass321',
            'protocol' => 'unix',
            'hostspec' => '',
            'port' => false,
            'socket' => '/var/run/mysql.sock',
            'database' => false,
        ];
        $this->assertEquals('proto(proto)://user123:pass321@unix(/var/run/mysql.sock)/', DB::getDSNString($parsedDsn, false));
    }

    public function testGetDsnStringWithProtoAndHost()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => 'user123',
            'password' => 'pass321',
            'protocol' => 'tcp',
            'hostspec' => 'myhost',
            'port' => false,
            'socket' => false,
            'database' => false,
        ];
        $this->assertEquals('proto(proto)://user123:pass321@tcp+myhost/', DB::getDSNString($parsedDsn, false));
    }

    public function testGetDsnStringWithHostAndPort()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => 'user123',
            'password' => 'pass321',
            'protocol' => 'tcp',
            'hostspec' => 'myhost',
            'port' => 1234,
            'socket' => false,
            'database' => false,
        ];
        $this->assertEquals('proto(proto)://user123:pass321@tcp+myhost:1234/', DB::getDSNString($parsedDsn, false));
    }

    public function testGetDsnStringWithDbName()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => 'dbname',
        ];
        $this->assertEquals('proto(proto)://:@tcp/dbname', DB::getDSNString($parsedDsn, false));
    }

    public function testGetDsnStringWithDbNameAndParameter()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => 'dbname',
            'utf8' => 'true',
        ];
        $this->assertEquals('proto(proto)://:@tcp/dbname?utf8=true', DB::getDSNString($parsedDsn, false));
    }

    public function testGetDsnStringWithDbNameAndParameters()
    {
        $parsedDsn = [
            'phptype' => 'proto',
            'dbsyntax' => 'proto',
            'username' => false,
            'password' => false,
            'protocol' => 'tcp',
            'hostspec' => '',
            'port' => false,
            'socket' => false,
            'database' => 'dbname',
            'utf8' => 'true',
            'banana' => 'pie',
        ];
        $this->assertEquals('proto(proto)://:@tcp/dbname?utf8=true&banana=pie', DB::getDSNString($parsedDsn, false));
    }
}
