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

    public function testIsConnection()
    {
        $dbh = DB::factory(TestDriver::class);
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
}
