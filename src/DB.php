<?php
namespace Pineapple;

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

    // @const Unknown error
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

    // @const The present user has inadequate permissions to perform the task requested
    const DB_ERROR_ACCESS_VIOLATION = -26;

    // @const The database requested does not exist
    const DB_ERROR_NOSUCHDB = -27;

    // @const Tried to insert a null value into a column that doesn't allow nulls
    const DB_ERROR_CONSTRAINT_NOT_NULL = -29;

    // @const It's possible you are about to do something you didn't realise was stupid
    const DB_ERROR_POSSIBLE_UNINTENDED_CONSEQUENCES = -30;

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

    // @const Driver class namespace prefix
    const INTERNAL_DRIVER_PREFIX = '\\Pineapple\\DB\\Driver\\';

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
        self::DB_ERROR_POSSIBLE_UNINTENDED_CONSEQUENCES => 'you may be about to do something stupid',
        self::DB_OK => 'no error',
    ];

    /**
     * Create a new DB object for the specified database type but don't
     * connect to the database
     *
     * @param string $type     the database driver name (eg "PdoDriver")
     * @param mixed  $options  an associative array of option names and values,
     *                         or a true/false value for the 'persistent'
     *                         option
     *
     * @return object          a new DB object. A DB\Error object on failure.
     *
     * @see DB\Driver\Common::setOption()
     */
    public static function factory($type, $options = false)
    {
        if (!is_array($options)) {
            $options = ['persistent' => $options];
        }

        $classname = self::qualifyClassname($type);

        if (!class_exists($classname)) {
            $tmp = Util::raiseError(
                null,
                self::DB_ERROR_NOT_FOUND,
                null,
                null,
                "Driver class for {$classname} is not available",
                DB\Error::class,
                true
            );
            return $tmp;
        }

        $obj = new $classname;

        foreach ($options as $option => $value) {
            $test = $obj->setOption($option, $value);
            if (self::isError($test)) {
                return $test;
            }
        }

        return $obj;
    }

    private static function qualifyClassname($class)
    {
        if (strpos($class, '\\') === false) {
            // @todo untestable in unit tests; remove annotation when adding integration test
            return self::INTERNAL_DRIVER_PREFIX . $class; // @codeCoverageIgnore
        }

        // this is fully qualified or a relative class, use verbatim
        return $class;
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
     * Return a textual error message for a DB error code
     *
     * @param integer|DB\Error $value  the DB error code
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
}
