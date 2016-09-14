<?php
namespace Mayden\Pineapple\Test\DB\Driver;

use Mayden\Pineapple\DB;
use Mayden\Pineapple\DB\Driver\DoctrineDbal;
use PHPUnit\Framework\TestCase;

class DoctrineDbalTest extends TestCase
{
    public function testConstruct()
    {
        $dbh = DB::connect(DoctrineDbal::class . '://');
        $this->assertInstanceOf(DoctrineDbal::class, $dbh);
    }
}
