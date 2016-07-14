<?php
namespace Mayden\Pineapple;

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

/**
 * Database independent query interface
 *
 * The main "DB" class is simply a container class with some static
 * methods for creating DB objects as well as some utility functions
 * common to all parts of DB.
 *
 * The object model of DB is as follows (indentation means inheritance):
 * <pre>
 * DB The main DB class.  This is simply a utility class
 *    with some "static" methods for creating DB objects as
 *    well as common utility functions for other DB classes.
 *
 * DB\Driver\Common The base for each DB implementation.  Provides default
 * |                implementations (in OO lingo virtual methods) for
 * |                the actual DB implementations as well as a bunch of
 * |                query utility functions.
 * |
 * +- DB\Driver\DoctrineDbal The driver implementation for DBAL. Inherits DB\Driver\Common.
 *                           When calling DB::factory or DB::connect for MySQL
 *                           connections, the object returned is an instance of this
 *                           class.
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
    /**
     * Portable status codes
     * @see DB\Driver\Common::errorCode(), DB\Driver\Common::errorMessage()
     */

    // @const The code returned by many methods upon success
    const DB_OK = 1;

    // @const Unkown error
    const DB_ERROR = -1;

    // @const Syntax error
    const DB_ERROR_SYNTAX = -2;

    // @const Tried to insert a duplicate value into a primary or unique index
    const DB_ERROR_CONSTRAINT = -3;

    // @const An identifier in the query refers to a non-existant object
    const DB_ERROR_NOT_FOUND = -4;

    // @const Tried to create a duplicate object
    const DB_ERROR_ALREADY_EXISTS = -5;

    // @const The current driver does not support the action you attempted
    const DB_ERROR_UNSUPPORTED = -6;

    // @const The number of parameters does not match the number of placeholders
    const DB_ERROR_MISMATCH = -7;

    // @const A literal submitted did not match the data type expected
    const DB_ERROR_INVALID = -8;

    // @const The current DBMS does not support the action you attempted
    const DB_ERROR_NOT_CAPABLE = -9;

    // @const A literal submitted was too long so the end of it was removed
    const DB_ERROR_TRUNCATED = -10;

    // @const A literal number submitted did not match the data type expected
    const DB_ERROR_INVALID_NUMBER = -11;

    // @const A literal date submitted did not match the data type expected
    const DB_ERROR_INVALID_DATE = -12;

    // @const Attempt to divide something by zero
    const DB_ERROR_DIVZERO = -13;

    // @const A database needs to be selected
    const DB_ERROR_NODBSELECTED = -14;

    // @const Could not create the object requested
    const DB_ERROR_CANNOT_CREATE = -15;

    // @const Could not drop the database requested because it does not exist
    const DB_ERROR_CANNOT_DROP = -17;

    // @const An identifier in the query refers to a non-existant table
    const DB_ERROR_NOSUCHTABLE = -18;

    // @const An identifier in the query refers to a non-existant column
    const DB_ERROR_NOSUCHFIELD = -19;

    // @const The data submitted to the method was inappropriate
    const DB_ERROR_NEED_MORE_DATA = -20;

    // @const The attempt to lock the table failed
    const DB_ERROR_NOT_LOCKED = -21;

    // @const The number of columns doesn't match the number of values
    const DB_ERROR_VALUE_COUNT_ON_ROW = -22;

    // @const The DSN submitted has problems
    const DB_ERROR_INVALID_DSN = -23;

    // @const Could not connect to the database
    const DB_ERROR_CONNECT_FAILED = -24;

    // @const The PHP extension needed for this DBMS could not be found
    const DB_ERROR_EXTENSION_NOT_FOUND = -25;

    // @const The present user has inadequate permissions to perform the task requestd
    const DB_ERROR_ACCESS_VIOLATION = -26;

    // @const The database requested does not exist
    const DB_ERROR_NOSUCHDB = -27;

    // @const Tried to insert a null value into a column that doesn't allow nulls
    const DB_ERROR_CONSTRAINT_NOT_NULL = -29;

    /**
     * Placeholder types
     * @see DB\Driver\Common::prepare()
     */

    // @const Indicates a scalar (?), will quote & escape
    const DB_PARAM_SCALAR = 1;

    // @const Indicates an opaque (&), bind value is a filename
    const DB_PARAM_OPAQUE = 2;

    // @const Indicates a misc (!), include verbatim
    const DB_PARAM_MISC = 3;

    /**
     * The different ways of returning data from queries.
     */

    // @const Sends the fetched data straight through to output
    const DB_BINMODE_PASSTHRU = 1;

    // @const Lets you return data as usual
    const DB_BINMODE_RETURN = 2;

    // @const Converts the data to hex format before returning it
    const DB_BINMODE_CONVERT = 3;

    /**
     * Fetch Modes
     * @see DB\Driver\Common::setFetchMode()
     */

    // @const The current default fetch mode (@see DB\Driver\Common::$fetchmode)
    const DB_FETCHMODE_DEFAULT = 0;

    // @const Column data indexed by numbers, ordered from 0 and up
    const DB_FETCHMODE_ORDERED = 1;

    // @const Column data indexed by column names
    const DB_FETCHMODE_ASSOC = 2;

    // @const Column data as object properties
    const DB_FETCHMODE_OBJECT = 3;

    // @const "flipped" format, where results are an array of columns, not an array of rows
    const DB_FETCHMODE_FLIPPED = 4;

    /**
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
    const DB_TABLEINFO_ORDER = 1;
    const DB_TABLEINFO_ORDERTABLE = 2;
    const DB_TABLEINFO_FULL = 3;

    /**
     * The type of query to create with the automatic query building methods.
     * @see DB\Driver\Common::autoPrepare(), DB\Driver\Common::autoExecute()
     */
    const DB_AUTOQUERY_INSERT = 1;
    const DB_AUTOQUERY_UPDATE = 2;

    /**
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

    // @const Turn off all portability features
    const DB_PORTABILITY_NONE = 0;

    // @const Convert names of tables and fields to lower case
    //        when using the get*(), fetch*() and tableInfo() methods
    const DB_PORTABILITY_LOWERCASE = 1;

    // @const Right trim the data output by get*() and fetch*()
    const DB_PORTABILITY_RTRIM = 2;

    // @const Force reporting the number of rows deleted
    const DB_PORTABILITY_DELETE_COUNT = 4;

    // @const Enable hack that makes numRows() work in Oracle
    const DB_PORTABILITY_NUMROWS = 8;

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
    // @const Convert error messages to a consistent type across DB layers
    const DB_PORTABILITY_ERRORS = 16;

    // @const Convert null values to empty strings in data output by get*() and fetch*()
    const DB_PORTABILITY_NULL_TO_EMPTY = 32;

    // @const Turn on all portability features
    const DB_PORTABILITY_ALL = 63;

    // @var error messages
    private static $errorMessages = [
        self::DB_ERROR => 'unknown error',
        self::DB_ERROR_ACCESS_VIOLATION => 'insufficient permissions',
        self::DB_ERROR_ALREADY_EXISTS => 'already exists',
        self::DB_ERROR_CANNOT_CREATE => 'can not create',
        self::DB_ERROR_CANNOT_DROP => 'can not drop',
        self::DB_ERROR_CONNECT_FAILED => 'connect failed',
        self::DB_ERROR_CONSTRAINT => 'constraint violation',
        self::DB_ERROR_CONSTRAINT_NOT_NULL => 'null value violates not-null constraint',
        self::DB_ERROR_DIVZERO => 'division by zero',
        self::DB_ERROR_EXTENSION_NOT_FOUND => 'extension not found',
        self::DB_ERROR_INVALID => 'invalid',
        self::DB_ERROR_INVALID_DATE => 'invalid date or time',
        self::DB_ERROR_INVALID_DSN => 'invalid DSN',
        self::DB_ERROR_INVALID_NUMBER => 'invalid number',
        self::DB_ERROR_MISMATCH => 'mismatch',
        self::DB_ERROR_NEED_MORE_DATA => 'insufficient data supplied',
        self::DB_ERROR_NODBSELECTED => 'no database selected',
        self::DB_ERROR_NOSUCHDB => 'no such database',
        self::DB_ERROR_NOSUCHFIELD => 'no such field',
        self::DB_ERROR_NOSUCHTABLE => 'no such table',
        self::DB_ERROR_NOT_CAPABLE => 'DB backend not capable',
        self::DB_ERROR_NOT_FOUND => 'not found',
        self::DB_ERROR_NOT_LOCKED => 'not locked',
        self::DB_ERROR_SYNTAX => 'syntax error',
        self::DB_ERROR_UNSUPPORTED => 'not supported',
        self::DB_ERROR_TRUNCATED => 'truncated',
        self::DB_ERROR_VALUE_COUNT_ON_ROW => 'value count on row',
        self::DB_OK => 'no error',
    ];

    /**
     * Create a new DB object for the specified database type but don't
     * connect to the database
     *
     * @param string $type     the database type (eg "mysql")
     * @param array  $options  an associative array of option names and values
     *
     * @return object  a new DB object.  A DB\Error object on failure.
     *
     * @see DB\Driver\Common::setOption()
     */
    public static function factory($type, $options = false)
    {
        if (!is_array($options)) {
            $options = ['persistent' => $options];
        }

        $classname = "\\Mayden\\Pineapple\\DB\\Driver\\${type}";

        if (!class_exists($classname)) {
            $tmp = Util::raiseError(
                null,
                self::DB_ERROR_NOT_FOUND,
                null,
                null,
                "Driver class for {$classname} is not available file for '{$dsn}'",
                DB\Error::class,
                true
            );
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

    /**
     * Create a new DB object including a connection to the specified database
     *
     * Example 1.
     *
     * <code>
     * $dsn = 'DoctrineDbal://';
     * $options = [
     *     'debug' => 2,
     *     'portability' => self::DB_PORTABILITY_ALL,
     * ];
     *
     * $db = DB::connect($dsn, $options);
     * if (DB\Error::isError($db)) {
     *     die($db->getMessage());
     * }
     * </code>
     *
     * @param mixed $dsn      the string "data source name" or array in the
     *                        format returned by DB::parseDSN()
     * @param array $options  an associative array of option names and values
     * @return object  a new DB object.  A DB\Error object on failure.
     * @uses DB\Driver\DoctrineDbal::connect()
     * @uses DB::parseDSN(), DB\Driver\Common::setOption(), DB\Error::isError()
     */
    public static function connect($dsn, $options = [])
    {
        $dsninfo = self::parseDSN($dsn);
        $type = $dsninfo['phptype'];

        if (!is_array($options)) {
            /*
             * For backwards compatibility.  $options used to be boolean,
             * indicating whether the connection should be persistent.
             */
            $options = ['persistent' => $options];
        }

        $classname = "\\Mayden\\Pineapple\\DB\\Driver\\${type}";

        if (!class_exists($classname)) {
            $tmp = Util::raiseError(
                null,
                self::DB_ERROR_NOT_FOUND,
                null,
                null,
                "Driver class for {$classname} is not available file for '" . self::getDSNString($dsn, true) . "'",
                DB\Error::class,
                true
            );
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

    /**
     * Determines if a variable is a DB\Error object
     *
     * @param mixed $value  the variable to check
     *
     * @return bool  whether $value is DB\Error object
     */
    public static function isError($value)
    {
        return is_object($value) && ($value instanceof DB\Error);
    }

    /**
     * Determines if a value is a DB_<driver> object
     *
     * @param mixed $value  the value to test
     *
     * @return bool  whether $value is a DB_<driver> object
     */
    public static function isConnection($value)
    {
        return is_object($value) && ($value instanceof DB\Driver\Common) && method_exists($value, 'simpleQuery');
    }

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
        $manips = implode('|', [
            'INSERT',
            'UPDATE',
            'DELETE',
            'REPLACE',
            'CREATE',
            'DROP',
            'LOAD DATA',
            'SELECT .* INTO .* FROM',
            'COPY',
            'ALTER',
            'GRANT',
            'REVOKE',
            'LOCK',
            'UNLOCK'
        ]);
        if (preg_match('/^\s*"?(' . $manips . ')\s+/i', $query)) {
            return true;
        }
        return false;
    }

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
        if (self::isError($value)) {
            $value = $value->getCode();
        }

        return isset(self::$errorMessages[$value]) ?
            self::$errorMessages[$value] :
            self::$errorMessages[self::DB_ERROR];
    }

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
        $parsed = [
            'phptype' => false,
            'dbsyntax' => false,
            'username' => false,
            'password' => false,
            'protocol' => false,
            'hostspec' => false,
            'port' => false,
            'socket' => false,
            'database' => false,
        ];

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
        if (($at = strrpos($dsn, '@')) !== false) {
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
                } else {
                    // /database?param1=value1
                    $opts = [$dsn];
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

    /**
     * Returns the given DSN in a string format suitable for output.
     *
     * @param array|string the DSN to parse and format
     * @param boolean true to hide the password, false to include it
     * @return string
     */
    public static function getDSNString($dsn, $hidePassword)
    {
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
            $dsnString .= '(' . $dsnArray['dbsyntax'] . ')';
        }
        $dsnString .= sprintf("://%s:%s@%s", $dsnArray['username'], $dsnArray['password'], $dsnArray['protocol']);
        if ($dsnArray['socket']) {
            $dsnString .= '(' . $dsnArray['socket'] . ')';
        }
        if ($dsnArray['protocol'] && $dsnArray['hostspec']) {
            $dsnString .= '+';
        }
        $dsnString .= $dsnArray['hostspec'];
        if ($dsnArray['port']) {
            $dsnString .= ':' . $dsnArray['port'];
        }
        $dsnString .= '/' . $dsnArray['database'];

        /**
         * Option handling. Unfortunately, parseDSN simply places options into
         * the top-level array, so we'll first get rid of the fields defined by
         * DB and see what's left.
         */
        unset(
            $dsnArray['phptype'],
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
                $dsnString .= $key . '=' . $value;
            }
        }

        return $dsnString;
    }
}
