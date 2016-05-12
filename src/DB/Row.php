<?php
namespace Mayden\Pineapple\DB;

/**
 * PEAR DB Row Object
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
    // {{{ constructor

    /**
     * The constructor places a row's data into properties of this object
     *
     * @param array  the array containing the row's data
     *
     * @return void
     */
    function __construct(&$arr)
    {
        foreach ($arr as $key => $value) {
            $this->$key = &$arr[$key];
        }
    }

    // }}}
}
