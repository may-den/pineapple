<?php
namespace Mayden\Pineapple\Test;

use Mayden\Pineapple\DB;
use Mayden\Pineapple\Test\DB\Driver\TestDriver;
use Mayden\Pineapple\Error;
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
        $this->markTestSkipped('@todo this test current fails');
        $dbh = DB::factory(TestDriver::class, ['q' => 'q who?']);
        $this->assertInstanceOf(TestDriver::class, $dbh);
        $this->assertEquals('q who?', $dbh->getOption('q'));
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

    public function testConnectWithOptions()
    {
        $dbh = DB::connect(TestDriver::class . '://', ['persistent' => false]);
        $this->assertInstanceOf(TestDriver::class, $dbh);
    }

    public function testConnectWithLegacyOption()
    {
        $dbh = DB::connect(TestDriver::class . '://', false);
        $this->assertInstanceOf(TestDriver::class, $dbh);
        $this->assertFalse($dbh->getOption('persistent'));
    }

    public function testConnectWithBadDriver()
    {
        $dbh = DB::connect('meep://');
        $this->assertInstanceOf(Error::class, $dbh);
    }
}
