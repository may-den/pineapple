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
}
