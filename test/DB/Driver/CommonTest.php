<?php
namespace Pineapple\Test\DB\Driver;

use Pineapple\DB;
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
}
