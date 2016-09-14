<?php
namespace Pineapple\Test\DB\Driver;

use Pineapple\DB;
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
}
