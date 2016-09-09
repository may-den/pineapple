<?php
namespace Mayden\Pineapple\Test\DB;

use Mayden\Pineapple\DB\Row;
use PHPUnit\Framework\TestCase;

class RowTest extends TestCase
{
    public function testConstruct()
    {
        $rowData = [
            'id' => 1,
            'name' => 'time borb',
        ];
        $row = new Row($rowData);
        $this->assertInstanceOf(Row::class, $row);
        $this->assertEquals('time borb', $row->name);
    }

    public function testSetValue()
    {
        $rowData = [
            'id' => 1,
            'name' => 'time borb',
        ];
        $row = new Row($rowData);
        $row->name = 'square borb';
        $this->assertEquals('square borb', $row->name);
    }

    public function testIsSetValue()
    {
        $rowData = [
            'id' => 1,
            'name' => 'time borb',
        ];
        $row = new Row($rowData);
        $this->assertTrue(isset($row->name));
    }

    public function testUnSetValue()
    {
        $rowData = [
            'id' => 1,
            'name' => 'time borb',
        ];
        $row = new Row($rowData);
        unset($row->name);
        $this->assertFalse(isset($row->name));
    }
}
