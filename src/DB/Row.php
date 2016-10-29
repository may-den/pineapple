<?php
namespace Pineapple\DB;

/**
 * Pineapple\DB Row Object
 *
 * The object contains a row of data from a result set.  Each column's data
 * is placed in a property named for the column.
 *
 * @category   Database
 * @package    DB
 * @author     Stig Bakken <ssb@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: 1.8.2
 * @link       http://pear.php.net/package/DB
 * @see        DB\Common::setFetchMode()
 */
class Row
{
    private $row = [];

    /**
     * The constructor places a row's data into properties of this object
     *
     * @param array  the array containing the row's data
     */
    public function __construct(&$arr)
    {
        foreach ($arr as $key => $value) {
            $this->row[$key] = &$arr[$key];
        }
    }

    /**
     * Magic __get to map to array
     *
     * @param mixed $key Name of the key to retrieve
     * @return mixed
     */
    public function __get($key)
    {
        return isset($this->row[$key]) ? $this->row[$key] : null;
    }

    /**
     * Magic __set to map to array
     *
     * @param mixed $key   Name of the key to set
     * @param mixed $value Value to set
     */
    public function __set($key, $value)
    {
        $this->row[$key] = $value;
    }

    /**
     * Magic __isset to map to array
     *
     * @param mixed $key Name of the key to check
     * @return boolean
     */
    public function __isset($key)
    {
        return isset($this->row[$key]);
    }

    /**
     * Magic __unset to map to array
     *
     * @param mixed $key Name of the key to unset
     */
    public function __unset($key)
    {
        unset($this->row[$key]);
    }
}
