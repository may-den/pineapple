<?php
namespace Pineapple\DB;

use Pineapple\Util;
use Pineapple\DB;
use Pineapple\Error as PineappleError;

/**
 * DB_Error implements a class for reporting portable database error
 * messages
 *
 * @category   Database
 * @package    DB
 * @author     Stig Bakken <ssb@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: 1.8.2
 * @link       http://pear.php.net/package/DB
 */
class Error extends PineappleError
{
    /**
     * DB_Error constructor
     *
     * @param mixed $code       DB error code, or string with error message
     * @param int   $mode       what "error mode" to operate in
     * @param int   $level      what error level to use for $mode &
     *                           PEAR_ERROR_TRIGGER
     * @param mixed $debuginfo  additional debug info, such as the last query
     *
     * @see PEAR_Error
     */
    public function __construct(
        $code = DB::DB_ERROR,
        $mode = Util::PEAR_ERROR_RETURN,
        $level = E_USER_NOTICE,
        $debuginfo = null
    )
    {
        if (is_int($code)) {
            parent::__construct(
                'DB Error: ' . DB::errorMessage($code),
                $code,
                $mode,
                $level,
                $debuginfo
            );
        } else {
            parent::__construct(
                "DB Error: $code",
                DB::DB_ERROR,
                $mode,
                $level,
                $debuginfo
            );
        }
    }
}
