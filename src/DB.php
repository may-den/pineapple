<?php
namespace Mayden\Pineapple;

use Mayden\Pineapple\Util as PEAR;

/**
 * Database independent query interface
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Database
 * @package    DB
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/DB
 */

// {{{ constants
// {{{ error codes

/**#@+
 * One of PEAR DB's portable error codes.
 * @see DB\Driver\Common::errorCode(), DB::errorMessage()
 *
 * {@internal If you add an error code here, make sure you also add a textual
 * version of it in DB::errorMessage().}}
 */

/**
 * The code returned by many methods upon success
 */
define('DB_OK', 1);

/**
 * Unkown error
 */
define('DB_ERROR', -1);

/**
 * Syntax error
 */
define('DB_ERROR_SYNTAX', -2);

/**
 * Tried to insert a duplicate value into a primary or unique index
 */
define('DB_ERROR_CONSTRAINT', -3);

/**
 * An identifier in the query refers to a non-existant object
 */
define('DB_ERROR_NOT_FOUND', -4);

/**
 * Tried to create a duplicate object
 */
define('DB_ERROR_ALREADY_EXISTS', -5);

/**
 * The current driver does not support the action you attempted
 */
define('DB_ERROR_UNSUPPORTED', -6);

/**
 * The number of parameters does not match the number of placeholders
 */
define('DB_ERROR_MISMATCH', -7);

/**
 * A literal submitted did not match the data type expected
 */
define('DB_ERROR_INVALID', -8);

/**
 * The current DBMS does not support the action you attempted
 */
define('DB_ERROR_NOT_CAPABLE', -9);

/**
 * A literal submitted was too long so the end of it was removed
 */
define('DB_ERROR_TRUNCATED', -10);

/**
 * A literal number submitted did not match the data type expected
 */
define('DB_ERROR_INVALID_NUMBER', -11);

/**
 * A literal date submitted did not match the data type expected
 */
define('DB_ERROR_INVALID_DATE', -12);

/**
 * Attempt to divide something by zero
 */
define('DB_ERROR_DIVZERO', -13);

/**
 * A database needs to be selected
 */
define('DB_ERROR_NODBSELECTED', -14);

/**
 * Could not create the object requested
 */
define('DB_ERROR_CANNOT_CREATE', -15);

/**
 * Could not drop the database requested because it does not exist
 */
define('DB_ERROR_CANNOT_DROP', -17);

/**
 * An identifier in the query refers to a non-existant table
 */
define('DB_ERROR_NOSUCHTABLE', -18);

/**
 * An identifier in the query refers to a non-existant column
 */
define('DB_ERROR_NOSUCHFIELD', -19);

/**
 * The data submitted to the method was inappropriate
 */
define('DB_ERROR_NEED_MORE_DATA', -20);

/**
 * The attempt to lock the table failed
 */
define('DB_ERROR_NOT_LOCKED', -21);

/**
 * The number of columns doesn't match the number of values
 */
define('DB_ERROR_VALUE_COUNT_ON_ROW', -22);

/**
 * The DSN submitted has problems
 */
define('DB_ERROR_INVALID_DSN', -23);

/**
 * Could not connect to the database
 */
define('DB_ERROR_CONNECT_FAILED', -24);

/**
 * The PHP extension needed for this DBMS could not be found
 */
define('DB_ERROR_EXTENSION_NOT_FOUND', -25);

/**
 * The present user has inadequate permissions to perform the task requestd
 */
define('DB_ERROR_ACCESS_VIOLATION', -26);

/**
 * The database requested does not exist
 */
define('DB_ERROR_NOSUCHDB', -27);

/**
 * Tried to insert a null value into a column that doesn't allow nulls
 */
define('DB_ERROR_CONSTRAINT_NOT_NULL', -29);
/**#@-*/


// }}}
// {{{ prepared statement-related


/**#@+
 * Identifiers for the placeholders used in prepared statements.
 * @see DB\Driver\Common::prepare()
 */

/**
 * Indicates a scalar (<kbd>?</kbd>) placeholder was used
 *
 * Quote and escape the value as necessary.
 */
define('DB_PARAM_SCALAR', 1);

/**
 * Indicates an opaque (<kbd>&</kbd>) placeholder was used
 *
 * The value presented is a file name.  Extract the contents of that file
 * and place them in this column.
 */
define('DB_PARAM_OPAQUE', 2);

/**
 * Indicates a misc (<kbd>!</kbd>) placeholder was used
 *
 * The value should not be quoted or escaped.
 */
define('DB_PARAM_MISC',   3);
/**#@-*/


// }}}
// {{{ binary data-related


/**#@+
 * The different ways of returning binary data from queries.
 */

/**
 * Sends the fetched data straight through to output
 */
define('DB_BINMODE_PASSTHRU', 1);

/**
 * Lets you return data as usual
 */
define('DB_BINMODE_RETURN', 2);

/**
 * Converts the data to hex format before returning it
 *
 * For example the string "123" would become "313233".
 */
define('DB_BINMODE_CONVERT', 3);
/**#@-*/


// }}}
// {{{ fetch modes


/**#@+
 * Fetch Modes.
 * @see DB\Driver\Common::setFetchMode()
 */

/**
 * Indicates the current default fetch mode should be used
 * @see DB\Driver\Common::$fetchmode
 */
define('DB_FETCHMODE_DEFAULT', 0);

/**
 * Column data indexed by numbers, ordered from 0 and up
 */
define('DB_FETCHMODE_ORDERED', 1);

/**
 * Column data indexed by column names
 */
define('DB_FETCHMODE_ASSOC', 2);

/**
 * Column data as object properties
 */
define('DB_FETCHMODE_OBJECT', 3);

/**
 * For multi-dimensional results, make the column name the first level
 * of the array and put the row number in the second level of the array
 *
 * This is flipped from the normal behavior, which puts the row numbers
 * in the first level of the array and the column names in the second level.
 */
define('DB_FETCHMODE_FLIPPED', 4);
/**#@-*/

/**#@+
 * Old fetch modes.  Left here for compatibility.
 */
define('DB_GETMODE_ORDERED', DB_FETCHMODE_ORDERED);
define('DB_GETMODE_ASSOC',   DB_FETCHMODE_ASSOC);
define('DB_GETMODE_FLIPPED', DB_FETCHMODE_FLIPPED);
/**#@-*/


// }}}
// {{{ tableInfo() && autoPrepare()-related


/**#@+
 * The type of information to return from the tableInfo() method.
 *
 * Bitwised constants, so they can be combined using <kbd>|</kbd>
 * and removed using <kbd>^</kbd>.
 *
 * @see DB\Driver\Common::tableInfo()
 *
 * {@internal Since the TABLEINFO constants are bitwised, if more of them are
 * added in the future, make sure to adjust DB_TABLEINFO_FULL accordingly.}}
 */
define('DB_TABLEINFO_ORDER', 1);
define('DB_TABLEINFO_ORDERTABLE', 2);
define('DB_TABLEINFO_FULL', 3);
/**#@-*/


/**#@+
 * The type of query to create with the automatic query building methods.
 * @see DB\Driver\Common::autoPrepare(), DB\Driver\Common::autoExecute()
 */
define('DB_AUTOQUERY_INSERT', 1);
define('DB_AUTOQUERY_UPDATE', 2);
/**#@-*/


// }}}
// {{{ portability modes


/**#@+
 * Portability Modes.
 *
 * Bitwised constants, so they can be combined using <kbd>|</kbd>
 * and removed using <kbd>^</kbd>.
 *
 * @see DB\Driver\Common::setOption()
 *
 * {@internal Since the PORTABILITY constants are bitwised, if more of them are
 * added in the future, make sure to adjust DB_PORTABILITY_ALL accordingly.}}
 */

/**
 * Turn off all portability features
 */
define('DB_PORTABILITY_NONE', 0);

/**
 * Convert names of tables and fields to lower case
 * when using the get*(), fetch*() and tableInfo() methods
 */
define('DB_PORTABILITY_LOWERCASE', 1);

/**
 * Right trim the data output by get*() and fetch*()
 */
define('DB_PORTABILITY_RTRIM', 2);

/**
 * Force reporting the number of rows deleted
 */
define('DB_PORTABILITY_DELETE_COUNT', 4);

/**
 * Enable hack that makes numRows() work in Oracle
 */
define('DB_PORTABILITY_NUMROWS', 8);

/**
 * Makes certain error messages in certain drivers compatible
 * with those from other DBMS's
 *
 * + mysql, mysqli:  change unique/primary key constraints
 *   DB_ERROR_ALREADY_EXISTS -> DB_ERROR_CONSTRAINT
 *
 * + odbc(access):  MS's ODBC driver reports 'no such field' as code
 *   07001, which means 'too few parameters.'  When this option is on
 *   that code gets mapped to DB_ERROR_NOSUCHFIELD.
 */
define('DB_PORTABILITY_ERRORS', 16);

/**
 * Convert null values to empty strings in data output by
 * get*() and fetch*()
 */
define('DB_PORTABILITY_NULL_TO_EMPTY', 32);

/**
 * Turn on all portability features
 */
define('DB_PORTABILITY_ALL', 63);
/**#@-*/

// }}}


// }}}
// {{{ class DB

/**
 * Database independent query interface
 *
 * The main "DB" class is simply a container class with some static
 * methods for creating DB objects as well as some utility functions
 * common to all parts of DB.
 *
 * The object model of DB is as follows (indentation means inheritance):
 * <pre>
 * DB           The main DB class.  This is simply a utility class
 *              with some "static" methods for creating DB objects as
 *              well as common utility functions for other DB classes.
 *
 * DB_common    The base for each DB implementation.  Provides default
 * |            implementations (in OO lingo virtual methods) for
 * |            the actual DB implementations as well as a bunch of
 * |            query utility functions.
 * |
 * +-DB_mysql   The DB implementation for MySQL.  Inherits DB_common.
 *              When calling DB::factory or DB::connect for MySQL
 *              connections, the object returned is an instance of this
 *              class.
 * </pre>
 *
 * @category   Database
 * @package    DB
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: 1.8.2
 * @link       http://pear.php.net/package/DB
 */
class DB
{
    // {{{ factory()

    /**
     * Create a new DB object for the specified database type but don't
     * connect to the database
     *
     * @param string $type     the database type (eg "mysql")
     * @param array  $options  an associative array of option names and values
     *
     * @return object  a new DB object.  A DB_Error object on failure.
     *
     * @see DB\Driver\Common::setOption()
     */
    public static function factory($type, $options = false)
    {
        if (!is_array($options)) {
            $options = array('persistent' => $options);
        }

        $classname = "\\Mayden\\Pineapple\\DB\\Driver\\${type}";

        if (!class_exists($classname)) {
            $tmp = PEAR::raiseError(null, DB_ERROR_NOT_FOUND, null, null,
                                    "Driver class for {$classname} is not available"
                                    . " file for '$dsn'",
                                    'DB_Error', true);
            return $tmp;
        }

        @$obj = new $classname;

        foreach ($options as $option => $value) {
            $test = $obj->setOption($option, $value);
            if (self::isError($test)) {
                return $test;
            }
        }

        return $obj;
    }

    // }}}
    // {{{ connect()

    /**
     * Create a new DB object including a connection to the specified database
     *
     * Example 1.
     * <code>
     * require_once 'DB.php';
     *
     * $dsn = 'pgsql://user:password@host/database';
     * $options = array(
     *     'debug'       => 2,
     *     'portability' => DB_PORTABILITY_ALL,
     * );
     *
     * $db = DB::connect($dsn, $options);
     * if (PEAR::isError($db)) {
     *     die($db->getMessage());
     * }
     * </code>
     *
     * @param mixed $dsn      the string "data source name" or array in the
     *                         format returned by DB::parseDSN()
     * @param array $options  an associative array of option names and values
     *
     * @return object  a new DB object.  A DB_Error object on failure.
     *
     * @uses DB_dbase::connect(), DB_fbsql::connect(), DB_ibase::connect(),
     *       DB_ifx::connect(), DB_msql::connect(), DB_mssql::connect(),
     *       DB_mysql::connect(), DB_mysqli::connect(), DB_oci8::connect(),
     *       DB_odbc::connect(), DB_pgsql::connect(), DB_sqlite::connect(),
     *       DB_sybase::connect()
     *
     * @uses DB::parseDSN(), DB\Driver\Common::setOption(), PEAR::isError()
     */
    public static function connect($dsn, $options = array())
    {
        $dsninfo = self::parseDSN($dsn);
        $type = $dsninfo['phptype'];

        if (!is_array($options)) {
            /*
             * For backwards compatibility.  $options used to be boolean,
             * indicating whether the connection should be persistent.
             */
            $options = array('persistent' => $options);
        }

        $classname = "\\Mayden\\Pineapple\\DB\\Driver\\${type}";

        if (!class_exists($classname)) {
            $tmp = PEAR::raiseError(null, DB_ERROR_NOT_FOUND, null, null,
                                    "Driver class for {$classname} is not available"
                                    . " file for '"
                                    . self::getDSNString($dsn, true) . "'",
                                    'DB_Error', true);
            return $tmp;
        }

        @$obj = new $classname;

        foreach ($options as $option => $value) {
            $test = $obj->setOption($option, $value);
            if (self::isError($test)) {
                return $test;
            }
        }

        $err = $obj->connect($dsninfo, $obj->getOption('persistent'));
        if (self::isError($err)) {
            if (is_array($dsn)) {
                $err->addUserInfo(self::getDSNString($dsn, true));
            } else {
                $err->addUserInfo($dsn);
            }
            return $err;
        }

        return $obj;
    }

    // }}}
    // {{{ apiVersion()

    /**
     * Return the DB API version
     *
     * @return string  the DB API version number
     */
    function apiVersion()
    {
        return '1.8.2';
    }

    // }}}
    // {{{ isError()

    /**
     * Determines if a variable is a DB_Error object
     *
     * @param mixed $value  the variable to check
     *
     * @return bool  whether $value is DB_Error object
     */
    public static function isError($value)
    {
        return is_object($value) && is_a($value, 'Mayden\Pineapple\DB\Error');
    }

    // }}}
    // {{{ isConnection()

    /**
     * Determines if a value is a DB_<driver> object
     *
     * @param mixed $value  the value to test
     *
     * @return bool  whether $value is a DB_<driver> object
     */
    public static function isConnection($value)
    {
        return (is_object($value) &&
                is_subclass_of($value, 'Mayden\Pineapple\DB\Driver\Common') &&
                method_exists($value, 'simpleQuery'));
    }

    // }}}
    // {{{ isManip()

    /**
     * Tell whether a query is a data manipulation or data definition query
     *
     * Examples of data manipulation queries are INSERT, UPDATE and DELETE.
     * Examples of data definition queries are CREATE, DROP, ALTER, GRANT,
     * REVOKE.
     *
     * @param string $query  the query
     *
     * @return boolean  whether $query is a data manipulation query
     */
    public static function isManip($query)
    {
        $manips = 'INSERT|UPDATE|DELETE|REPLACE|'
                . 'CREATE|DROP|'
                . 'LOAD DATA|SELECT .* INTO .* FROM|COPY|'
                . 'ALTER|GRANT|REVOKE|'
                . 'LOCK|UNLOCK';
        if (preg_match('/^\s*"?(' . $manips . ')\s+/i', $query)) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ errorMessage()

    /**
     * Return a textual error message for a DB error code
     *
     * @param integer $value  the DB error code
     *
     * @return string  the error message or false if the error code was
     *                  not recognized
     */
    public static function errorMessage($value)
    {
        static $errorMessages;
        if (!isset($errorMessages)) {
            $errorMessages = array(
                DB_ERROR                    => 'unknown error',
                DB_ERROR_ACCESS_VIOLATION   => 'insufficient permissions',
                DB_ERROR_ALREADY_EXISTS     => 'already exists',
                DB_ERROR_CANNOT_CREATE      => 'can not create',
                DB_ERROR_CANNOT_DROP        => 'can not drop',
                DB_ERROR_CONNECT_FAILED     => 'connect failed',
                DB_ERROR_CONSTRAINT         => 'constraint violation',
                DB_ERROR_CONSTRAINT_NOT_NULL=> 'null value violates not-null constraint',
                DB_ERROR_DIVZERO            => 'division by zero',
                DB_ERROR_EXTENSION_NOT_FOUND=> 'extension not found',
                DB_ERROR_INVALID            => 'invalid',
                DB_ERROR_INVALID_DATE       => 'invalid date or time',
                DB_ERROR_INVALID_DSN        => 'invalid DSN',
                DB_ERROR_INVALID_NUMBER     => 'invalid number',
                DB_ERROR_MISMATCH           => 'mismatch',
                DB_ERROR_NEED_MORE_DATA     => 'insufficient data supplied',
                DB_ERROR_NODBSELECTED       => 'no database selected',
                DB_ERROR_NOSUCHDB           => 'no such database',
                DB_ERROR_NOSUCHFIELD        => 'no such field',
                DB_ERROR_NOSUCHTABLE        => 'no such table',
                DB_ERROR_NOT_CAPABLE        => 'DB backend not capable',
                DB_ERROR_NOT_FOUND          => 'not found',
                DB_ERROR_NOT_LOCKED         => 'not locked',
                DB_ERROR_SYNTAX             => 'syntax error',
                DB_ERROR_UNSUPPORTED        => 'not supported',
                DB_ERROR_TRUNCATED          => 'truncated',
                DB_ERROR_VALUE_COUNT_ON_ROW => 'value count on row',
                DB_OK                       => 'no error',
            );
        }

        if (self::isError($value)) {
            $value = $value->getCode();
        }

        return isset($errorMessages[$value]) ? $errorMessages[$value]
                     : $errorMessages[DB_ERROR];
    }

    // }}}
    // {{{ parseDSN()

    /**
     * Parse a data source name
     *
     * Additional keys can be added by appending a URI query string to the
     * end of the DSN.
     *
     * The format of the supplied DSN is in its fullest form:
     * <code>
     *  phptype(dbsyntax)://username:password@protocol+hostspec/database?option=8&another=true
     * </code>
     *
     * Most variations are allowed:
     * <code>
     *  phptype://username:password@protocol+hostspec:110//usr/db_file.db?mode=0644
     *  phptype://username:password@hostspec/database_name
     *  phptype://username:password@hostspec
     *  phptype://username@hostspec
     *  phptype://hostspec/database
     *  phptype://hostspec
     *  phptype(dbsyntax)
     *  phptype
     * </code>
     *
     * @param string $dsn Data Source Name to be parsed
     *
     * @return array an associative array with the following keys:
     *  + phptype:  Database backend used in PHP (mysql, odbc etc.)
     *  + dbsyntax: Database used with regards to SQL syntax etc.
     *  + protocol: Communication protocol to use (tcp, unix etc.)
     *  + hostspec: Host specification (hostname[:port])
     *  + database: Database to use on the DBMS server
     *  + username: User name for login
     *  + password: Password for login
     */
    public static function parseDSN($dsn)
    {
        $parsed = array(
            'phptype'  => false,
            'dbsyntax' => false,
            'username' => false,
            'password' => false,
            'protocol' => false,
            'hostspec' => false,
            'port'     => false,
            'socket'   => false,
            'database' => false,
        );

        if (is_array($dsn)) {
            $dsn = array_merge($parsed, $dsn);
            if (!$dsn['dbsyntax']) {
                $dsn['dbsyntax'] = $dsn['phptype'];
            }
            return $dsn;
        }

        // Find phptype and dbsyntax
        if (($pos = strpos($dsn, '://')) !== false) {
            $str = substr($dsn, 0, $pos);
            $dsn = substr($dsn, $pos + 3);
        } else {
            $str = $dsn;
            $dsn = null;
        }

        // Get phptype and dbsyntax
        // $str => phptype(dbsyntax)
        if (preg_match('|^(.+?)\((.*?)\)$|', $str, $arr)) {
            $parsed['phptype']  = $arr[1];
            $parsed['dbsyntax'] = !$arr[2] ? $arr[1] : $arr[2];
        } else {
            $parsed['phptype']  = $str;
            $parsed['dbsyntax'] = $str;
        }

        if (!count($dsn)) {
            return $parsed;
        }

        // Get (if found): username and password
        // $dsn => username:password@protocol+hostspec/database
        if (($at = strrpos($dsn,'@')) !== false) {
            $str = substr($dsn, 0, $at);
            $dsn = substr($dsn, $at + 1);
            if (($pos = strpos($str, ':')) !== false) {
                $parsed['username'] = rawurldecode(substr($str, 0, $pos));
                $parsed['password'] = rawurldecode(substr($str, $pos + 1));
            } else {
                $parsed['username'] = rawurldecode($str);
            }
        }

        // Find protocol and hostspec

        if (preg_match('|^([^(]+)\((.*?)\)/?(.*?)$|', $dsn, $match)) {
            // $dsn => proto(proto_opts)/database
            $proto       = $match[1];
            $proto_opts  = $match[2] ? $match[2] : false;
            $dsn         = $match[3];

        } else {
            // $dsn => protocol+hostspec/database (old format)
            if (strpos($dsn, '+') !== false) {
                list($proto, $dsn) = explode('+', $dsn, 2);
            }
            if (strpos($dsn, '/') !== false) {
                list($proto_opts, $dsn) = explode('/', $dsn, 2);
            } else {
                $proto_opts = $dsn;
                $dsn = null;
            }
        }

        // process the different protocol options
        $parsed['protocol'] = (!empty($proto)) ? $proto : 'tcp';
        $proto_opts = rawurldecode($proto_opts);
        if (strpos($proto_opts, ':') !== false) {
            list($proto_opts, $parsed['port']) = explode(':', $proto_opts);
        }
        if ($parsed['protocol'] == 'tcp') {
            $parsed['hostspec'] = $proto_opts;
        } elseif ($parsed['protocol'] == 'unix') {
            $parsed['socket'] = $proto_opts;
        }

        // Get dabase if any
        // $dsn => database
        if ($dsn) {
            if (($pos = strpos($dsn, '?')) === false) {
                // /database
                $parsed['database'] = rawurldecode($dsn);
            } else {
                // /database?param1=value1&param2=value2
                $parsed['database'] = rawurldecode(substr($dsn, 0, $pos));
                $dsn = substr($dsn, $pos + 1);
                if (strpos($dsn, '&') !== false) {
                    $opts = explode('&', $dsn);
                } else { // database?param1=value1
                    $opts = array($dsn);
                }
                foreach ($opts as $opt) {
                    list($key, $value) = explode('=', $opt);
                    if (!isset($parsed[$key])) {
                        // don't allow params overwrite
                        $parsed[$key] = rawurldecode($value);
                    }
                }
            }
        }

        return $parsed;
    }

    // }}}
    // {{{ getDSNString()

    /**
     * Returns the given DSN in a string format suitable for output.
     *
     * @param array|string the DSN to parse and format
     * @param boolean true to hide the password, false to include it
     * @return string
     */
    public static function getDSNString($dsn, $hidePassword) {
        /* Calling parseDSN will ensure that we have all the array elements
         * defined, and means that we deal with strings and array in the same
         * manner. */
        $dsnArray = self::parseDSN($dsn);

        if ($hidePassword) {
            $dsnArray['password'] = 'PASSWORD';
        }

        /* Protocol is special-cased, as using the default "tcp" along with an
         * Oracle TNS connection string fails. */
        if (is_string($dsn) && strpos($dsn, 'tcp') === false && $dsnArray['protocol'] == 'tcp') {
            $dsnArray['protocol'] = false;
        }

        // Now we just have to construct the actual string. This is ugly.
        $dsnString = $dsnArray['phptype'];
        if ($dsnArray['dbsyntax']) {
            $dsnString .= '('.$dsnArray['dbsyntax'].')';
        }
        $dsnString .= '://'
                     .$dsnArray['username']
                     .':'
                     .$dsnArray['password']
                     .'@'
                     .$dsnArray['protocol'];
        if ($dsnArray['socket']) {
            $dsnString .= '('.$dsnArray['socket'].')';
        }
        if ($dsnArray['protocol'] && $dsnArray['hostspec']) {
            $dsnString .= '+';
        }
        $dsnString .= $dsnArray['hostspec'];
        if ($dsnArray['port']) {
            $dsnString .= ':'.$dsnArray['port'];
        }
        $dsnString .= '/'.$dsnArray['database'];

        /* Option handling. Unfortunately, parseDSN simply places options into
         * the top-level array, so we'll first get rid of the fields defined by
         * DB and see what's left. */
        unset($dsnArray['phptype'],
              $dsnArray['dbsyntax'],
              $dsnArray['username'],
              $dsnArray['password'],
              $dsnArray['protocol'],
              $dsnArray['socket'],
              $dsnArray['hostspec'],
              $dsnArray['port'],
              $dsnArray['database']
        );
        if (count($dsnArray) > 0) {
            $dsnString .= '?';
            $i = 0;
            foreach ($dsnArray as $key => $value) {
                if (++$i > 1) {
                    $dsnString .= '&';
                }
                $dsnString .= $key.'='.$value;
            }
        }

        return $dsnString;
    }

    // }}}
}
