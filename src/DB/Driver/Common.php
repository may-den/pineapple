<?php
namespace Pineapple\DB\Driver;

use Pineapple\Util;
use Pineapple\DB;
use Pineapple\DB\Result;
use Pineapple\DB\Error;
use Pineapple\DB\Exception\FeatureException;
use Pineapple\DB\StatementContainer;

use stdClass;

/**
 * Pineapple Common driver functions. Override in drivers to provide
 * implementation-specific differences.
 *
 * Mostly _not_ written by (see below for authors), slightly modernised by:
 *
 * @author     Rob Andrews <rob@aphlor.org>
 *
 * Retained comment & license from DB:
 *
 * Common is the base class from which each database driver class extends
 *
 * All common methods are declared here.  If a given DBMS driver contains
 * a particular method, that method will overload the one here.
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
 * @author     Tomas V.V. Cox <cox@idecnet.com>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       http://pear.php.net/package/DB
 */
abstract class Common extends Util
{
    /** @var integer The current default fetch mode */
    protected $fetchmode = DB::DB_FETCHMODE_ORDERED;

    /**
     * The name of the class into which results should be fetched when
     * DB_FETCHMODE_OBJECT is in effect
     *
     * @var string
     */
    protected $fetchModeObjectClass = stdClass::class;

    /**
     * Was a connection present when the object was `serialize()`ed?
     *
     * @var boolean
     * @see Common::__sleep(), Common::__wake()
     */
    protected $wasConnected = null;

    /**
     * The most recently executed query
     * @var string
     * @todo replace with an accessor
     */
    public $lastQuery = '';

    /** @var boolean A flag to indicate that the author is prepared to make some poor life choices */
    protected $acceptConsequencesOfPoorCodingChoices = false;

    /** @var mixed Database connection handle */
    protected $connection = null;

    /**
     * Run-time configuration options
     *
     * @var array
     * @see Common::setOption()
     */
    protected $options = [
        'result_buffering' => 500,
        'debug' => 0,
        'seqname_format' => '%s_seq', // Still referenced in pineapple-compat
        'autofree' => false,
        'portability' => DB::DB_PORTABILITY_NONE,
        'strict_transactions' => true,
    ];

    /**
     * The parameters from the most recently executed query
     * @var array
     * @since Property available since Release 1.7.0
     * @todo Replace with in accessor
     */
    public $lastParameters = [];

    /** @var array The elements from each prepared statement */
    protected $prepareTokens = [];

    /** @var array The data types of the various elements in each prepared statement */
    protected $prepareTypes = [];

    /** @var array The prepared queries */
    protected $preparedQueries = [];

    /** @var boolean Flag indicating that the last query was a manipulation query */
    protected $lastQueryManip = false;

    /** @var boolean Flag indicating that the next query _must_ be a manipulation query */
    protected $nextQueryManip = false;

    /**
     * The capabilities of the DB implementation
     *
     * Meaning of the 'limit' element:
     *   + 'emulate' = emulate with fetch row by number
     *   + 'alter'   = alter the query
     *   + false     = skip rows
     *
     * @var array
     */
    protected $features = [];

    /**
     * This constructor calls <kbd>parent::__construct('Pineapple\DB\Error')</kbd>
     */
    public function __construct()
    {
        parent::__construct(Error::class);
    }

    /**
     * Automatically indicates which properties should be saved
     * when PHP's serialize() function is called
     *
     * @return array  the array of properties names that should be saved
     */
    public function __sleep()
    {
        $this->wasConnected = false;

        if ($this->connected()) {
            // Don't disconnect(), people use serialize() for many reasons
            $this->wasConnected = true;
        }

        $toSerialize = [
            'features',
            'fetchmode',
            'fetchModeObjectClass',
            'options',
            'wasConnected',
            'errorClass',
        ];
        if (isset($this->autocommit)) {
            $toSerialize = array_merge(['autocommit'], $toSerialize);
        }
        return $toSerialize;
    }

    /**
     * Automatic string conversion for PHP 5
     *
     * @return string  a string describing the current PEAR DB object
     *
     * @since Method available since Release 1.7.0
     */
    public function __toString()
    {
        $info = get_class($this);

        if ($this->connected()) {
            $info .= ' [connected]';
        }

        return $info;
    }


    /**
     * Gets an advertised feature of the driver
     *
     * @param string $feature Name of the feature to return
     */
    public function getFeature($feature)
    {
        if (!isset($this->features[$feature])) {
            throw new FeatureException('Feature \"{$feature}\" not advertised by driver');
        }
        return $this->features[$feature];
    }

    /**
     * Accept that your UPDATE without a WHERE is going to update a lot of
     * data and that you understand the consequences.
     *
     * @param boolean $flag true to make UPDATE without WHERE work
     * @since Method available since Pineapple 0.1.0
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function setAcceptConsequencesOfPoorCodingChoices($flag = false)
    {
        $this->acceptConsequencesOfPoorCodingChoices = $flag ? true : false;
    }

    /**
     * Quotes a string so it can be safely used as a table or column name
     *
     * Delimiting style depends on which database driver is being used.
     *
     * NOTE: just because you CAN use delimited identifiers doesn't mean
     * you SHOULD use them.  In general, they end up causing way more
     * problems than they solve.
     *
     * Portability is broken by using the following characters inside
     * delimited identifiers:
     *   + backtick (<kbd>`</kbd>) -- due to MySQL
     *   + double quote (<kbd>"</kbd>) -- due to Oracle
     *   + brackets (<kbd>[</kbd> or <kbd>]</kbd>) -- due to Access
     *
     * Delimited identifiers are known to generally work correctly under
     * the following drivers:
     *   + mssql
     *   + mysql
     *   + mysqli
     *   + oci8
     *   + odbc(access)
     *   + odbc(db2)
     *   + pgsql
     *   + sqlite
     *   + sybase (must execute <kbd>set quoted_identifier on</kbd> sometime
     *     prior to use)
     *
     * InterBase doesn't seem to be able to use delimited identifiers
     * via PHP 4.  They work fine under PHP 5.
     *
     * @param string $str  the identifier name to be quoted
     *
     * @return string  the quoted identifier
     *
     * @since Method available since Release 1.6.0
     */
    public function quoteIdentifier($str)
    {
        return '"' . str_replace('"', '""', $str) . '"';
    }

    /**
     * Formats input so it can be safely used in a query
     *
     * The output depends on the PHP data type of input and the database
     * type being used.
     *
     * @param mixed $property the data to be formatted
     *
     * @return mixed          the formatted data.  The format depends on the
     *                        input's PHP type:
     * <ul>
     *  <li>
     *    <kbd>input</kbd> -> <samp>returns</samp>
     *  </li>
     *  <li>
     *    <kbd>null</kbd> -> the string <samp>NULL</samp>
     *  </li>
     *  <li>
     *    <kbd>integer</kbd> or <kbd>double</kbd> -> the unquoted number
     *  </li>
     *  <li>
     *    <kbd>bool</kbd> -> output depends on the driver in use
     *    Most drivers return integers: <samp>1</samp> if
     *    <kbd>true</kbd> or <samp>0</samp> if
     *    <kbd>false</kbd>.
     *    Some return strings: <samp>TRUE</samp> if
     *    <kbd>true</kbd> or <samp>FALSE</samp> if
     *    <kbd>false</kbd>.
     *    Finally one returns strings: <samp>T</samp> if
     *    <kbd>true</kbd> or <samp>F</samp> if
     *    <kbd>false</kbd>. Here is a list of each DBMS,
     *    the values returned and the suggested column type:
     *    <ul>
     *      <li>
     *        <kbd>dbase</kbd> -> <samp>T/F</samp>
     *        (<kbd>Logical</kbd>)
     *      </li>
     *      <li>
     *        <kbd>fbase</kbd> -> <samp>TRUE/FALSE</samp>
     *        (<kbd>BOOLEAN</kbd>)
     *      </li>
     *      <li>
     *        <kbd>ibase</kbd> -> <samp>1/0</samp>
     *        (<kbd>SMALLINT</kbd>) [1]
     *      </li>
     *      <li>
     *        <kbd>ifx</kbd> -> <samp>1/0</samp>
     *        (<kbd>SMALLINT</kbd>) [1]
     *      </li>
     *      <li>
     *        <kbd>msql</kbd> -> <samp>1/0</samp>
     *        (<kbd>INTEGER</kbd>)
     *      </li>
     *      <li>
     *        <kbd>mssql</kbd> -> <samp>1/0</samp>
     *        (<kbd>BIT</kbd>)
     *      </li>
     *      <li>
     *        <kbd>mysql</kbd> -> <samp>1/0</samp>
     *        (<kbd>TINYINT(1)</kbd>)
     *      </li>
     *      <li>
     *        <kbd>mysqli</kbd> -> <samp>1/0</samp>
     *        (<kbd>TINYINT(1)</kbd>)
     *      </li>
     *      <li>
     *        <kbd>oci8</kbd> -> <samp>1/0</samp>
     *        (<kbd>NUMBER(1)</kbd>)
     *      </li>
     *      <li>
     *        <kbd>odbc</kbd> -> <samp>1/0</samp>
     *        (<kbd>SMALLINT</kbd>) [1]
     *      </li>
     *      <li>
     *        <kbd>pgsql</kbd> -> <samp>TRUE/FALSE</samp>
     *        (<kbd>BOOLEAN</kbd>)
     *      </li>
     *      <li>
     *        <kbd>sqlite</kbd> -> <samp>1/0</samp>
     *        (<kbd>INTEGER</kbd>)
     *      </li>
     *      <li>
     *        <kbd>sybase</kbd> -> <samp>1/0</samp>
     *        (<kbd>TINYINT(1)</kbd>)
     *      </li>
     *    </ul>
     *    [1] Accommodate the lowest common denominator because not all
     *    versions of have <kbd>BOOLEAN</kbd>.
     *  </li>
     *  <li>
     *    other (including strings and numeric strings) ->
     *    the data with single quotes escaped by preceeding
     *    single quotes, backslashes are escaped by preceeding
     *    backslashes, then the whole string is encapsulated
     *    between single quotes
     *  </li>
     * </ul>
     *
     * @see Common::escapeSimple()
     * @since Method available since Release 1.6.0
     */
    public function quoteSmart($property)
    {
        if (is_int($property)) {
            return $property;
        } elseif (is_float($property)) {
            return $this->quoteFloat($property);
        } elseif (is_bool($property)) {
            return $this->quoteBoolean($property);
        } elseif (is_null($property)) {
            return 'NULL';
        } else {
            return "'" . $this->escapeSimple($property) . "'";
        }
    }

    /**
     * Formats a boolean value for use within a query in a locale-independent
     * manner.
     *
     * @param boolean the boolean value to be quoted.
     * @return string the quoted string.
     * @see Common::quoteSmart()
     * @since Method available since release 1.7.8.
     */
    protected function quoteBoolean($boolean)
    {
        return $boolean ? '1' : '0';
    }

    /**
     * Formats a float value for use within a query in a locale-independent
     * manner.
     *
     * @param float the float value to be quoted.
     * @return string the quoted string.
     * @see Common::quoteSmart()
     * @since Method available since release 1.7.8.
     */
    protected function quoteFloat($float)
    {
        return "'".$this->escapeSimple(str_replace(',', '.', strval(floatval($float))))."'";
    }

    /**
     * Escapes a string according to the current DBMS's standards
     *
     * In SQLite, this makes things safe for inserts/updates, but may
     * cause problems when performing text comparisons against columns
     * containing binary data. See the
     * {@link http://php.net/sqlite_escape_string PHP manual} for more info.
     *
     * @param string $str  the string to be escaped
     *
     * @return string  the escaped string
     *
     * @see Common::quoteSmart()
     * @since Method available since Release 1.6.0
     */
    public function escapeSimple($str)
    {
        return str_replace("'", "''", $str);
    }

    /**
     * Tells whether the present driver supports a given feature
     *
     * @param string $feature  the feature you're curious about
     *
     * @return mixed Usually boolean, whether this driver supports $feature,
     *               in the case of limit a string
     */
    public function provides($feature)
    {
        return $this->features[$feature];
    }

    /**
     * Sets the fetch mode that should be used by default for query results
     *
     * @param integer $fetchmode    DB_FETCHMODE_ORDERED, DB_FETCHMODE_ASSOC
     *                               or DB_FETCHMODE_OBJECT
     * @param string $objectClass   the class name of the object to be returned
     *                               by the fetch methods when the
     *                               DB_FETCHMODE_OBJECT mode is selected.
     *                               If no class is specified by default a cast
     *                               to object from the assoc array row will be
     *                               done.  There is also the posibility to use
     *                               and extend the 'Pineapple\DB\Row' class.
     *
     * @see DB_FETCHMODE_ORDERED, DB_FETCHMODE_ASSOC, DB_FETCHMODE_OBJECT
     */
    public function setFetchMode($fetchmode, $objectClass = stdClass::class)
    {
        switch ($fetchmode) {
            case DB::DB_FETCHMODE_OBJECT:
                $this->fetchModeObjectClass = $objectClass;
                // no break here deliberately
            case DB::DB_FETCHMODE_ORDERED:
            case DB::DB_FETCHMODE_ASSOC:
                $this->fetchmode = $fetchmode;
                break;
            default:
                return $this->raiseError('invalid fetchmode mode');
        }
    }

    /**
     * Gets the fetch mode that is used by default for query result
     *
     * @return integer A value representing DB::DB_FETCHMODE_* constant
     * @see DB::DB_FETCHMODE_ASSOC
     * @see DB::DB_FETCHMODE_ORDERED
     * @see DB::DB_FETCHMODE_OBJECT
     * @see DB::DB_FETCHMODE_DEFAULT
     * @see DB::DB_FETCHMODE_FLIPPED
     */
    public function getFetchMode()
    {
        return $this->fetchmode;
    }

    /**
     * Gets the class used to map rows into objects for DB::DB_FETCHMODE_OBJECT
     *
     * @return string The class used to map rows
     * @see Pineapple\DB\Row
     */
    public function getFetchModeObjectClass()
    {
        return $this->fetchModeObjectClass;
    }

    /**
     * Sets run-time configuration options
     *
     * Options, their data types, default values and description:
     *
     * - `autofree` (boolean) = `false`
     *   should results be freed automatically when there are no more rows?
     *
     * - `result_buffering` (integer) = `500`
     *   how many rows of the result set should be buffered?
     *   Supported by `PdoDriver`, not by `DoctrineDbal`
     *
     * - `debug` (integer) = `0`
     *   debug level
     *
     * - `portability` (integer) = `DB_PORTABILITY_NONE`
     *   portability mode constant (see below)
     *
     * - `seqname_format` (string) = `%s_seq`
     *   the sprintf() format string used on sequence names. This format is
     *   applied to sequence names passed to `createSequence()`, `nextID()`
     *   and `dropSequence()`.
     *
     * -----------------------------------------
     *
     * PORTABILITY MODES
     *
     * These modes are bitwised, so they can be combined using `|` and
     * removed using `^`. See the examples section below on how to do this.
     *
     * - `DB_PORTABILITY_NONE`
     *   turn off all portability features
     *
     * - `DB_PORTABILITY_LOWERCASE`
     *   convert names of tables and fields to lower case when using
     *   `get*()`, `fetch*()` and `tableInfo()`
     *
     * - `DB_PORTABILITY_RTRIM`
     *   right trim the data output by `get*()` `fetch*()`
     *
     * - `DB_PORTABILITY_DELETE_COUNT`
     *   force reporting the number of rows deleted
     *
     *   Some DBMS's don't count the number of rows deleted when performing
     *   simple `DELETE FROM tablename` queries.  This portability
     *   mode tricks such DBMS's into telling the count by adding
     *   `WHERE 1=1` to the end of `DELETE` queries.
     *
     * - `DB_PORTABILITY_NUMROWS`
     *   enable hack that makes `numRows()` work in Oracle
     *
     *   + mysql, mysqli:  change unique/primary key constraints
     *     DB_ERROR_ALREADY_EXISTS -> DB_ERROR_CONSTRAINT
     *
     * - `DB_PORTABILITY_NULL_TO_EMPTY</samp>
     *   convert null values to empty strings in data output by get*() and
     *   fetch*().  Needed because Oracle considers empty strings to be null,
     *   while most other DBMS's know the difference between empty and null.
     *
     * - `DB_PORTABILITY_ALL</samp>
     *   turn on all portability features
     *
     * -----------------------------------------
     *
     * Example 1. Simple setOption() example
     * ```php
     * $db->setOption('autofree', true);
     * ```
     *
     * Example 2. Portability for lowercasing and trimming
     * ```php
     * $db->setOption('portability',
     *                 DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_RTRIM);
     * ```
     *
     * Example 3. All portability options except trimming
     * ```php
     * $db->setOption(
     *     'portability',
     *     DB::DB_PORTABILITY_ALL ^ DB::DB_PORTABILITY_RTRIM
      * );
     * ```
     *
     * @param string $option option name
     * @param mixed  $value  value for the option
     * @return mixed         DB_OK on success. A Pineapple\DB\Error object on failure.
     *
     * @see Common::$options
     */
    public function setOption($option, $value)
    {
        if (isset($this->options[$option])) {
            $this->options[$option] = $value;
            return DB::DB_OK;
        }
        return $this->raiseError("unknown option $option");
    }

    /**
     * Returns the value of an option
     *
     * @param string $option  the option name you're curious about
     * @return mixed  the option's value
     */
    public function getOption($option)
    {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        }
        return $this->raiseError("unknown option $option");
    }

    /**
     * Determine if we're connected
     *
     * @return boolean true if connected, false if not
     */
    public function connected()
    {
        if (isset($this->connection) && $this->connection) {
            return true;
        }
        return false;
    }

    /**
     * Prepares a query for multiple execution with execute()
     *
     * Creates a query that can be run multiple times.  Each time it is run,
     * the placeholders, if any, will be replaced by the contents of
     * execute()'s $data argument.
     *
     * Three types of placeholders can be used:
     *   + `?`  scalar value (i.e. strings, integers).  The system will
     *          automatically quote and escape the data.
     *   + `!`  value is inserted 'as is'
     *   + `&`  requires a file name.  The file's contents get inserted
     *          into the query (i.e. saving binary data in a db)
     *
     * Example 1.
     * ```php
     * $sth = $db->prepare('INSERT INTO tbl (a, b, c) VALUES (?, !, &)');
     * $data = [
     *     "John's text",
     *     "'it''s good'",
     *     'filename.txt'
     * ];
     * $res = $db->execute($sth, $data);
     * ```
     *
     * Use backslashes to escape placeholder characters if you don't want
     * them to be interpreted as placeholders:
     * ```sql
     * UPDATE foo SET col=? WHERE col='over \& under'
     * ```
     *
     * With some database backends, this is emulated.
     *
     * @param string $query  the query to be prepared
     * @return mixed         DB statement resource on success. A Pineapple\DB\Error
     *                       object on failure.
     *
     * @see Common::execute()
     */
    public function prepare($query)
    {
        $tokens = preg_split('/((?<!\\\)[&?!])/', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
        $token = 0;
        $types = [];
        $newtokens = [];

        foreach ($tokens as $val) {
            switch ($val) {
                case '?':
                    $types[$token++] = DB::DB_PARAM_SCALAR;
                    break;
                case '&':
                    $types[$token++] = DB::DB_PARAM_OPAQUE;
                    break;
                case '!':
                    $types[$token++] = DB::DB_PARAM_MISC;
                    break;
                default:
                    $newtokens[] = preg_replace('/\\\([&?!])/', "\\1", $val);
            }
        }

        $this->prepareTokens[] = &$newtokens;
        end($this->prepareTokens);

        $key = key($this->prepareTokens);
        $this->prepareTypes[$key] = $types;
        $this->preparedQueries[$key] = implode(' ', $newtokens);

        return $key;
    }

    /**
     * Automaticaly generates an insert or update query and pass it to prepare()
     *
     * @param string $table         the table name
     * @param array  $tableFields   the array of field names
     * @param int    $mode          a type of query to make:
     *                              DB_AUTOQUERY_INSERT or DB_AUTOQUERY_UPDATE
     * @param string $where         for update queries: the WHERE clause to
     *                              append to the SQL statement.  Don't
     *                              include the "WHERE" keyword.
     * @return mixed                the query handle
     *
     * @uses Common::prepare(), Common::buildManipSQL()
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function autoPrepare($table, $tableFields, $mode = DB::DB_AUTOQUERY_INSERT, $where = null)
    {
        $query = $this->buildManipSQL($table, $tableFields, $mode, $where);
        if (DB::isError($query)) {
            return $query;
        }
        return $this->prepare($query);
    }

    /**
     * Automaticaly generates an insert or update query and call prepare()
     * and execute() with it
     *
     * @param string $table         the table name
     * @param array  $fieldsValues  the associative array where $key is a
     *                              field name and $value its value
     * @param int    $mode          a type of query to make:
     *                              DB_AUTOQUERY_INSERT or DB_AUTOQUERY_UPDATE
     * @param string $where         for update queries: the WHERE clause to
     *                              append to the SQL statement.  Don't
     *                              include the "WHERE" keyword.
     * @return mixed                a new Result object for successful SELECT queries
     *                              or DB_OK for successul data manipulation queries.
     *                              A Pineapple\DB\Error object on failure.
     *
     * @uses Common::autoPrepare(), Common::execute()
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function autoExecute($table, $fieldsValues, $mode = DB::DB_AUTOQUERY_INSERT, $where = null)
    {
        $sth = $this->autoPrepare($table, array_keys($fieldsValues), $mode, $where);
        if (DB::isError($sth)) {
            return $sth;
        }
        $ret = $this->execute($sth, array_values($fieldsValues));
        $this->freePrepared($sth);
        return $ret;
    }

    /**
     * Produces an SQL query string for autoPrepare()
     *
     * Example:
     * ```php
     * buildManipSQL('table_sql', ['field1', 'field2', 'field3'],
     *               DB_AUTOQUERY_INSERT);
     * ```
     *
     * That returns
     * ```sql
     * INSERT INTO table_sql (field1,field2,field3) VALUES (?,?,?)
     * ```
     *
     * NOTES:
     *   - This belongs more to a SQL Builder class, but this is a simple
     *     facility.
     *   - Be carefull! If you don't give a $where param with an UPDATE
     *     query, all the records of the table will be updated!
     *
     * @param string $table         the table name
     * @param array  $tableFields   the array of field names
     * @param int    $mode          a type of query to make:
     *                              DB_AUTOQUERY_INSERT or DB_AUTOQUERY_UPDATE
     * @param string $where         for update queries: the WHERE clause to
     *                              append to the SQL statement.  Don't
     *                              include the "WHERE" keyword.
     * @return string|Error         the sql query for autoPrepare(), or an Error
     *                              object in the case of failure
     */
    public function buildManipSQL($table, $tableFields, $mode, $where = null)
    {
        if (count($tableFields) == 0) {
            return $this->raiseError(DB::DB_ERROR_NEED_MORE_DATA);
        }
        $first = true;
        switch ($mode) {
            case DB::DB_AUTOQUERY_INSERT:
                $values = '';
                $names = '';
                foreach ($tableFields as $value) {
                    if ($first) {
                        $first = false;
                    } else {
                        $names .= ',';
                        $values .= ',';
                    }
                    $names .= $value;
                    $values .= '?';
                }
                return "INSERT INTO $table ($names) VALUES ($values)";
            case DB::DB_AUTOQUERY_UPDATE:
                if ((empty(trim($where)) || $where === null) &&
                    $this->acceptConsequencesOfPoorCodingChoices === false) {
                    return $this->raiseError(DB::DB_ERROR_POSSIBLE_UNINTENDED_CONSEQUENCES);
                }

                $set = '';

                foreach ($tableFields as $value) {
                    if ($first) {
                        $first = false;
                    } else {
                        $set .= ',';
                    }
                    $set .= "$value = ?";
                }
                $sql = "UPDATE $table SET $set";

                if (($where !== null) && $where) {
                    $sql .= " WHERE $where";
                }
                return $sql;
            default:
                return $this->raiseError(DB::DB_ERROR_SYNTAX);
        }
    }

    /**
     * Executes a DB statement prepared with prepare()
     *
     * Example 1.
     * ```php
     * $sth = $db->prepare('INSERT INTO tbl (a, b, c) VALUES (?, !, &)');
     * $data = [
     *     "John's text",
     *     "'it''s good'",
     *     'filename.txt'
     * ];
     * $res = $db->execute($sth, $data);
     * ```
     *
     * @param int      $stmt  a DB statement number returned from prepare()
     * @param mixed    $data  array, string or numeric data to be used in
     *                        execution of the statement.  Quantity of items
     *                        passed must match quantity of placeholders in
     *                        query:  meaning 1 placeholder for non-array
     *                        parameters or 1 placeholder per array element.
     * @return mixed          a new Result object for successful SELECT queries
     *                        or DB_OK for successul data manipulation queries.
     *                        A Pineapple\DB\Error object on failure.
     *
     * @see Common::prepare()
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function execute($stmt, $data = [])
    {
        $realquery = $this->executeEmulateQuery($stmt, $data);
        if (DB::isError($realquery)) {
            return $realquery;
        }
        $result = $this->simpleQuery($realquery);

        if ($result === DB::DB_OK || DB::isError($result)) {
            return $result;
        } else {
            $tmp = new Result($this, $result);
            return $tmp;
        }
    }

    /**
     * Emulates executing prepared statements if the DBMS not support them
     *
     * @param int   $stmt  a DB statement number returned from execute()
     * @param mixed $data  array, string or numeric data to be used in
     *                     execution of the statement.  Quantity of items
     *                     passed must match quantity of placeholders in
     *                     query:  meaning 1 placeholder for non-array
     *                     parameters or 1 placeholder per array element.
     * @return mixed       a string containing the real query run when emulating
     *                     prepare/execute.  A Pineapple\DB\Error object on failure.
     *
     * @see Common::execute()
     */
    protected function executeEmulateQuery($stmt, $data = [])
    {
        $stmt = (int) $stmt;
        $data = (array) $data;
        $this->lastParameters = $data;

        if (count($this->prepareTypes[$stmt]) != count($data)) {
            $this->lastQuery = $this->preparedQueries[$stmt];
            return $this->raiseError(DB::DB_ERROR_MISMATCH);
        }

        $realquery = $this->prepareTokens[$stmt][0];

        $bindPosition = 0;
        foreach ($data as $value) {
            if ($this->prepareTypes[$stmt][$bindPosition] == DB::DB_PARAM_SCALAR) {
                $realquery .= $this->quoteSmart($value);
            } elseif ($this->prepareTypes[$stmt][$bindPosition] == DB::DB_PARAM_OPAQUE) {
                $fp = @fopen($value, 'rb');
                if (!$fp) {
                    // @codeCoverageIgnoreStart
                    // @todo this is a pain to test without vfsStream, so skip for now
                    return $this->raiseError(DB::DB_ERROR_ACCESS_VIOLATION);
                    // @codeCoverageIgnoreEnd
                }
                $realquery .= $this->quoteSmart(fread($fp, filesize($value)));
                fclose($fp);
            } else {
                $realquery .= $value;
            }

            $realquery .= $this->prepareTokens[$stmt][++$bindPosition];
        }

        return $realquery;
    }

    /**
     * Performs several execute() calls on the same statement handle
     *
     * $data must be an array indexed numerically
     * from 0, one execute call is done for every "row" in the array.
     *
     * If an error occurs during execute(), executeMultiple() does not
     * execute the unfinished rows, but rather returns that error.
     *
     * @param int   $stmt  query handle from prepare()
     * @param array $data  numeric array containing the data to insert
     *                     into the query
     * @return int         DB_OK on success. A Pineapple\DB\Error object on failure.
     *
     * @see Common::prepare(), Common::execute()
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function executeMultiple($stmt, array $data)
    {
        foreach ($data as $value) {
            $res = $this->execute($stmt, $value);
            if (DB::isError($res)) {
                return $res;
            }
        }
        return DB::DB_OK;
    }

    /**
     * Frees the internal resources associated with a prepared query
     *
     * @param int  $stmt         the prepared statement's ID number
     * @param bool $freeResource should the PHP resource be freed too? Use
     *                           false if you need to get data from the
     *                           result set later.
     * @return bool              true on success, false if $result is invalid
     *
     * @see Common::prepare()
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function freePrepared($stmt, $freeResource = true)
    {
        $stmt = (int)$stmt;
        if (isset($this->prepareTokens[$stmt])) {
            unset($this->prepareTokens[$stmt]);
            unset($this->prepareTypes[$stmt]);
            unset($this->preparedQueries[$stmt]);
            return true;
        }
        return false;
    }

    /**
     * Changes a query string for various DBMS specific reasons
     *
     * It is defined here to ensure all drivers have this method available.
     *
     * @param string $query  the query string to modify
     * @return string        the modified query string
     *
     * @see DB\Driver\DoctrineDbal::modifyQuery()
     */
    protected function modifyQuery($query)
    {
        return $query;
    }

    /**
     * Adds LIMIT clauses to a query string according to current DBMS standards
     *
     * It is defined here to assure that all implementations
     * have this method defined.
     *
     * @param string $query   the query to modify
     * @param int    $from    the row to start to fetching (0 = the first row)
     * @param int    $count   the numbers of rows to fetch
     * @param mixed  $params  array, string or numeric data to be used in
     *                        execution of the statement.  Quantity of items
     *                        passed must match quantity of placeholders in
     *                        query:  meaning 1 placeholder for non-array
     *                        parameters or 1 placeholder per array element.
     *
     * @return string  the query string with LIMIT clauses added
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function modifyLimitQuery($query, $from, $count, $params = [])
    {
        return $query;
    }

    /**
     * Sends a query to the database server
     *
     * The query string can be either a normal statement to be sent directly
     * to the server OR if `$params` are passed the query can have
     * placeholders and it will be passed through prepare() and execute().
     *
     * @param string $query   the SQL query or the statement to prepare
     * @param mixed  $params  array, string or numeric data to be used in
     *                        execution of the statement.  Quantity of items
     *                        passed must match quantity of placeholders in
     *                        query:  meaning 1 placeholder for non-array
     *                        parameters or 1 placeholder per array element.
     * @return mixed          a new Result object for successful SELECT queries
     *                        or DB_OK for successul data manipulation queries.
     *                        A Pineapple\DB\Error object on failure.
     *
     * @see Result, Common::prepare(), Common::execute()
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function query($query, $params = [])
    {
        if (count($params) > 0) {
            $sth = $this->prepare($query);
            if (DB::isError($sth)) {
                return $sth;
            }
            $ret = $this->execute($sth, $params);
            $this->freePrepared($sth, false);
            return $ret;
        }

        $this->lastParameters = [];
        $result = $this->simpleQuery($query);
        if ($result === DB::DB_OK || DB::isError($result)) {
            return $result;
        }

        $tmp = new Result($this, $result);
        return $tmp;
    }

    /**
     * Generates and executes a LIMIT query
     *
     * @param string $query   the query
     * @param int    $from    the row to start to fetching (0 = the first row)
     * @param int    $count   the numbers of rows to fetch
     * @param mixed  $params  array, string or numeric data to be used in
     *                        execution of the statement.  Quantity of items
     *                        passed must match quantity of placeholders in
     *                        query:  meaning 1 placeholder for non-array
     *                        parameters or 1 placeholder per array element.
     * @return mixed          a new Result object for successful SELECT queries
     *                        or DB_OK for successul data manipulation queries.
     *                        A Pineapple\DB\Error object on failure.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function limitQuery($query, $from, $count, $params = [])
    {
        $query = $this->modifyLimitQuery($query, $from, $count, $params);
        if (DB::isError($query)) {
            return $query;
        }
        $result = $this->query($query, $params);
        if (is_object($result) && ($result instanceof Result)) {
            $result->setOption('limit_from', $from);
            $result->setOption('limit_count', $count);
        }
        return $result;
    }

    /**
     * Fetches the first column of the first row from a query result
     *
     * Takes care of doing the query and freeing the results when finished.
     *
     * @param string $query   the SQL query
     * @param mixed  $params  array, string or numeric data to be used in
     *                        execution of the statement.  Quantity of items
     *                        passed must match quantity of placeholders in
     *                        query:  meaning 1 placeholder for non-array
     *                        parameters or 1 placeholder per array element.
     * @return mixed          the returned value of the query.
     *                        A Pineapple\DB\Error object on failure.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getOne($query, $params = [])
    {
        $row = null;
        $params = (array) $params;
        // modifyLimitQuery() would be nice here, but it causes BC issues
        if (count($params) > 0) {
            $sth = $this->prepare($query);
            if (DB::isError($sth)) {
                return $sth;
            }
            $res = $this->execute($sth, $params);
            $this->freePrepared($sth);
        } else {
            $res = $this->query($query);
        }

        if (DB::isError($res)) {
            return $res;
        }

        $err = $res->fetchInto($row, DB::DB_FETCHMODE_ORDERED);
        $res->free();

        if ($err !== DB::DB_OK) {
            return $err;
        }

        return $row[0];
    }

    /**
     * Fetches the first row of data returned from a query result
     *
     * Takes care of doing the query and freeing the results when finished.
     *
     * @param string $query   the SQL query
     * @param mixed  $params  array, string or numeric data to be used in
     *                        execution of the statement.  Quantity of items
     *                        passed must match quantity of placeholders in
     *                        query:  meaning 1 placeholder for non-array
     *                        parameters or 1 placeholder per array element.
     * @param int $fetchmode  the fetch mode to use
     * @return array|Error    the first row of results as an array.
     *                        A Pineapple\DB\Error object on failure.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getRow($query, $params = [], $fetchmode = DB::DB_FETCHMODE_DEFAULT)
    {
        /**
         * there used to be a backward compatibility thing here that checked
         * for the possibility of transposed $params and $fetchmode.
         *
         * i'm not in any way sorry to say: it's gone. another "nice" feature
         * to permit you to send a scalar value instead of a single-element
         * array made this complicated, and in that situation, the parameter
         * was lost, and absorbed into fetchmode.
         *
         * only, this made things complicated. at first it wasn't obvious,
         * but fetchmode is checked with bitwise ops, so when php 7.1 strict
         * bitwise came along, and started comparing strings with bit ops, it
         * broke.
         */
        if (!is_array($params)) {
            $params = [$params];
        }

        // modifyLimitQuery() would be nice here, but it causes BC issues
        if (count($params) > 0) {
            $sth = $this->prepare($query);
            if (DB::isError($sth)) {
                return $sth;
            }
            $res = $this->execute($sth, $params);
            $this->freePrepared($sth);
        } else {
            $res = $this->query($query);
        }

        if (DB::isError($res)) {
            return $res;
        }

        $row = [];
        $err = $res->fetchInto($row, $fetchmode);

        $res->free();

        if ($err !== DB::DB_OK) {
            return $err;
        }

        return $row;
    }

    /**
     * Fetches a single column from a query result and returns it as an
     * indexed array
     *
     * @param string $query   the SQL query
     * @param mixed  $col     which column to return (integer [column number,
     *                        starting at 0] or string [column name])
     * @param mixed  $params  array, string or numeric data to be used in
     *                        execution of the statement.  Quantity of items
     *                        passed must match quantity of placeholders in
     *                        query:  meaning 1 placeholder for non-array
     *                        parameters or 1 placeholder per array element.
     * @return array          the results as an array. A Pineapple\DB\Error object on failure.
     *
     * @see Common::query()
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getCol($query, $col = 0, $params = [])
    {
        $params = (array) $params;
        if (count($params) > 0) {
            $sth = $this->prepare($query);

            if (DB::isError($sth)) {
                return $sth;
            }

            $res = $this->execute($sth, $params);
            $this->freePrepared($sth);
        } else {
            $res = $this->query($query);
        }

        if (DB::isError($res)) {
            return $res;
        }

        $fetchmode = is_int($col) ? DB::DB_FETCHMODE_ORDERED : DB::DB_FETCHMODE_ASSOC;

        if (!is_array($row = $res->fetchRow($fetchmode))) {
            $ret = [];
        } else {
            if (!array_key_exists($col, $row)) {
                $ret = $this->raiseError(DB::DB_ERROR_NOSUCHFIELD);
            } else {
                $ret = [$row[$col]];
                while (is_array($row = $res->fetchRow($fetchmode))) {
                    $ret[] = $row[$col];
                }
            }
        }

        $res->free();

        if (DB::isError($row)) {
            $ret = $row;
        }

        return $ret;
    }

    /**
     * Fetches an entire query result and returns it as an
     * associative array using the first column as the key
     *
     * If the result set contains more than two columns, the value
     * will be an array of the values from column 2-n.  If the result
     * set contains only two columns, the returned value will be a
     * scalar with the value of the second column (unless forced to an
     * array with the $forceArray parameter).  A DB error code is
     * returned on errors.  If the result set contains fewer than two
     * columns, a DB_ERROR_TRUNCATED error is returned.
     *
     * For example, if the table "mytable" contains:
     *
     * | ID | TEXT    | DATE      |
     * |----|---------|-----------|
     * | 1  | 'one'   | 944679408 |
     * | 2  | 'two'   | 944679408 |
     * | 3  | 'three' | 944679408 |
     *
     * Then the call `getAssoc('SELECT id,text FROM mytable')` returns:
     * ```php
     *   [
     *     '1' => 'one',
     *     '2' => 'two',
     *     '3' => 'three',
     *   ]
     * ```
     *
     * ...while the call `getAssoc('SELECT id,text,date FROM mytable')` returns:
     * ```php
     *   [
     *     '1' => ['one', '944679408'],
     *     '2' => ['two', '944679408'],
     *     '3' => ['three', '944679408']
     *   ]
     * ```
     *
     * If the more than one row occurs with the same value in the
     * first column, the last row overwrites all previous ones by
     * default.  Use the $group parameter if you don't want to
     * overwrite like this.  Example:
     *
     * `getAssoc('SELECT category,id,name FROM mytable', false, null,
     *          DB_FETCHMODE_ASSOC, true)` returns:
     *
     * ```php
     *   [
     *     '1' => [
     *       ['id' => '4', 'name' => 'number four'],
     *       ['id' => '6', 'name' => 'number six']
     *     ],
     *     '9' => [
     *       ['id' => '4', 'name' => 'number four'],
     *       ['id' => '6', 'name' => 'number six']
     *     ]
     *   ]
     * ```
     *
     * Keep in mind that database functions in PHP usually return string
     * values for results regardless of the database's internal type.
     *
     * @param string $query        the SQL query
     * @param bool   $forceArray   used only when the query returns
     *                             exactly two columns.  If true, the values
     *                             of the returned array will be one-element
     *                             arrays instead of scalars.
     * @param mixed  $params       array, string or numeric data to be used in
     *                             execution of the statement.  Quantity of
     *                             items passed must match quantity of
     *                             placeholders in query:  meaning 1
     *                             placeholder for non-array parameters or
     *                             1 placeholder per array element.
     * @param int   $fetchmode     the fetch mode to use
     * @param bool  $group         if true, the values of the returned array
     *                             is wrapped in another array.  If the same
     *                             key value (in the first column) repeats
     *                             itself, the values will be appended to
     *                             this array instead of overwriting the
     *                             existing values.
     *
     * @return array|Error         the associative array containing the query results.
     *                             A Pineapple\DB\Error object on failure.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getAssoc(
        $query,
        $forceArray = false,
        $params = [],
        $fetchmode = DB::DB_FETCHMODE_DEFAULT,
        $group = false
    ) {
        $row = null;
        $params = (array) $params;
        if (count($params) > 0) {
            $sth = $this->prepare($query);

            if (DB::isError($sth)) {
                return $sth;
            }

            $res = $this->execute($sth, $params);
            $this->freePrepared($sth);
        } else {
            $res = $this->query($query);
        }

        if (DB::isError($res)) {
            return $res;
        }
        if ($fetchmode == DB::DB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        $cols = $res->numCols();

        if ($cols < 2) {
            $tmp = $this->raiseError(DB::DB_ERROR_TRUNCATED);
            return $tmp;
        }

        $results = [];

        if ($cols > 2 || $forceArray) {
            // return array values
            // @todo this part can be optimized

            /**
             * @todo I'm acutely aware that this is probably a 50-line bug, because:
             * - there's no bitwise ops (meaning combined bits will fall to else block)
             * - it's likely combined flip won't work either
             * - result doesn't handle bitwise either
             * - the (possibly combined) fetchmode isn't passed into fetchRow()
             * "fixing" this would possibly introduce bugs in code that depend on the
             * broken behaviour, so fixing this does _not_ seem like a priority.
             */
            if ($fetchmode == DB::DB_FETCHMODE_ASSOC) {
                while (is_array($row = $res->fetchRow(DB::DB_FETCHMODE_ASSOC))) {
                    reset($row);
                    $key = current($row);
                    unset($row[key($row)]);
                    if ($group) {
                        $results[$key][] = $row;
                    } else {
                        $results[$key] = $row;
                    }
                }
            } elseif ($fetchmode == DB::DB_FETCHMODE_OBJECT) {
                while ($row = $res->fetchRow(DB::DB_FETCHMODE_OBJECT)) {
                    $arr = get_object_vars($row);
                    $key = current($arr);
                    if ($group) {
                        $results[$key][] = $row;
                    } else {
                        $results[$key] = $row;
                    }
                }
            } else {
                while (is_array($row = $res->fetchRow(DB::DB_FETCHMODE_ORDERED))) {
                    // we shift away the first element to get
                    // indices running from 0 again
                    $key = array_shift($row);
                    if ($group) {
                        $results[$key][] = $row;
                    } else {
                        $results[$key] = $row;
                    }
                }
            }
            if (DB::isError($row)) {
                $results = $row;
            }
        } else {
            // return scalar values
            while (is_array($row = $res->fetchRow(DB::DB_FETCHMODE_ORDERED))) {
                if ($group) {
                    $results[$row[0]][] = $row[1];
                } else {
                    $results[$row[0]] = $row[1];
                }
            }
            if (DB::isError($row)) {
                $results = $row;
            }
        }

        $res->free();

        return $results;
    }

    /**
     * Fetches all of the rows from a query result
     *
     * @param string $query     the SQL query
     * @param mixed  $params    array, string or numeric data to be used in
     *                          execution of the statement.  Quantity of
     *                          items passed must match quantity of
     *                          placeholders in query:  meaning 1
     *                          placeholder for non-array parameters or
     *                          1 placeholder per array element.
     * @param int    $fetchmode the fetch mode to use:
     *                          - DB_FETCHMODE_ORDERED
     *                          - DB_FETCHMODE_ASSOC
     *                          - DB_FETCHMODE_ORDERED | DB_FETCHMODE_FLIPPED
     *                          - DB_FETCHMODE_ASSOC | DB_FETCHMODE_FLIPPED
     * @return array            the nested array. A Pineapple\DB\Error object on failure.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getAll($query, $params = [], $fetchmode = DB::DB_FETCHMODE_DEFAULT)
    {
        // see comment at the top of getRow() - transposed $params and
        // $fetchmode is no longer supported.
        if (!is_array($params)) {
            $params = [$params];
        }

        if (count($params) > 0) {
            $sth = $this->prepare($query);

            if (DB::isError($sth)) {
                return $sth;
            }

            $res = $this->execute($sth, $params);
            $this->freePrepared($sth);
        } else {
            $res = $this->query($query);
        }

        if ($res === DB::DB_OK || DB::isError($res)) {
            return $res;
        }

        $results = [];
        $row = [];
        while (DB::DB_OK === $res->fetchInto($row, $fetchmode)) {
            if ($fetchmode & DB::DB_FETCHMODE_FLIPPED) {
                foreach ($row as $key => $val) {
                    $results[$key][] = $val;
                }
            } else {
                $results[] = $row;
            }
        }

        $res->free();

        return $results;
    }

    /**
     * Enables or disables automatic commits
     *
     * @param bool $onoff true turns it on, false turns it off
     * @return int|Error  DB_OK on success. A Pineapple\DB\Error object if
     *                    the driver doesn't support auto-committing transactions.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function autoCommit($onoff = false)
    {
        return $this->raiseError(DB::DB_ERROR_NOT_CAPABLE);
    }

    /**
     * Commits the current transaction
     *
     * @return int|Error DB_OK on success. A Pineapple\DB\Error object on failure.
     */
    public function commit()
    {
        return $this->raiseError(DB::DB_ERROR_NOT_CAPABLE);
    }

    /**
     * Reverts the current transaction
     *
     * @return int|Error DB_OK on success. A Pineapple\DB\Error object on failure.
     */
    public function rollback()
    {
        return $this->raiseError(DB::DB_ERROR_NOT_CAPABLE);
    }

    /**
     * Determines the number of rows in a query result
     *
     * @param StatementContainer $result the query result idenifier produced by PHP
     * @return int|Error                 the number of rows.  A Pineapple\DB\Error object on failure.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function numRows(StatementContainer $result)
    {
        return $this->raiseError(DB::DB_ERROR_NOT_CAPABLE);
    }

    /**
     * Determines the number of rows affected by a data maniuplation query
     *
     * 0 is returned for queries that don't manipulate data.
     *
     * @return int|Error  the number of rows.  A Pineapple\DB\Error object on failure.
     */
    public function affectedRows()
    {
        return $this->raiseError(DB::DB_ERROR_NOT_CAPABLE);
    }

    /**
     * Communicates an error and invoke error callbacks, etc
     *
     * Basically a wrapper for Pineapple\Util::raiseError without the message string.
     *
     * @param mixed  integer error code, or a Pineapple\Error object (all
     *               other parameters are ignored if this parameter is
     *               an object
     * @param int    error mode, see Pineapple\Error docs
     * @param mixed  if error mode is PEAR_ERROR_TRIGGER, this is the
     *               error level (E_USER_NOTICE etc).  If error mode is
     *               PEAR_ERROR_CALLBACK, this is the callback function,
     *               either as a function name, or as an array of an
     *               object and method name. For other error modes this
     *               parameter is ignored.
     * @param string extra debug information. Defaults to the last
     *               query and native error code.
     * @param mixed  native error code, integer or string depending the
     *               backend
     * @param mixed  dummy parameter for E_STRICT compatibility with
     *               Pineapple\Util::raiseError
     * @param mixed  dummy parameter for E_STRICT compatibility with
     *               Pineapple\Util::raiseError
     * @return Error the Pineapple\Error object
     *
     * @see Pineapple\Error
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function raiseError(
        $code = DB::DB_ERROR,
        $mode = null,
        $options = null,
        $userInfo = null,
        $nativecode = null,
        $dummy1 = null,
        $dummy2 = null
    ) {
        // The error is yet a DB error object
        if (is_object($code)) {
            $tmp = Util::raiseError($code, null, $mode, $options, null, null, true);
            return $tmp;
        }

        if ($userInfo === null) {
            $userInfo = $this->lastQuery;
        }

        if ($nativecode) {
            $userInfo .= ' [nativecode=' . trim($nativecode) . ']';
        } else {
            $userInfo .= ' [DB Error: ' . DB::errorMessage($code) . ']';
        }

        $tmp = Util::raiseError(null, $code, $mode, $options, $userInfo, Error::class, true);
        return $tmp;
    }

    /**
     * Gets the DBMS' native error code produced by the last query
     *
     * @return mixed  the DBMS' error code.  A Pineapple\DB\Error object on failure.
     */
    public function errorNative()
    {
        return $this->raiseError(DB::DB_ERROR_NOT_CAPABLE);
    }

    /**
     * Maps native error codes to DB's portable ones
     *
     * @param string|int $nativecode the error code returned by the DBMS
     * @return int                   the portable DB error code.  Return DB_ERROR if the
     *                               current driver doesn't have a mapping for the
     *                               $nativecode submitted.
     */
    public function errorCode($nativecode)
    {
        // @todo put this into -compat and refactor out this method
        return $this->getNativeErrorCode($nativecode);
    }

    /**
     * Maps a DB error code to a textual message
     *
     * @param int $dbcode the DB error code
     * @return string     the error message corresponding to the error code
     *                    submitted.  FALSE if the error code is unknown.
     *
     * @see DB::errorMessage()
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function errorMessage($dbcode)
    {
        return DB::errorMessage($this->getNativeErrorCode($dbcode));
    }

    /**
     * Returns information about a table or a result set
     *
     * The format of the resulting array depends on which <var>$mode</var>
     * you select.  The sample output below is based on this query:
     * ```sql
     *    SELECT tblFoo.fldID, tblFoo.fldPhone, tblBar.fldId
     *    FROM tblFoo
     *    JOIN tblBar ON tblFoo.fldId = tblBar.fldId
     * ```
     *
     * `null` (default)
     *   ```php
     *   [0] => Array (
     *       [table] => tblFoo
     *       [name] => fldId
     *       [type] => int
     *       [len] => 11
     *       [flags] => primary_key not_null
     *   )
     *   [1] => Array (
     *       [table] => tblFoo
     *       [name] => fldPhone
     *       [type] => string
     *       [len] => 20
     *       [flags] =>
     *   )
     *   [2] => Array (
     *       [table] => tblBar
     *       [name] => fldId
     *       [type] => int
     *       [len] => 11
     *       [flags] => primary_key not_null
     *   )
     *   ```
     *
     * `DB_TABLEINFO_ORDER`
     *
     * In addition to the information found in the default output, a notation
     * of the number of columns is provided by the `num_fields` element while
     * the `order` element provides an array with the column names as the keys
     * and their location index number (corresponding to the keys in the
     * default output) as the values.
     *
     * If a result set has identical field names, the last one is used.
     *
     *   ```php
     *   [num_fields] => 3
     *   [order] => Array (
     *       [fldId] => 2
     *       [fldTrans] => 1
     *   )
     *   ```
     *
     * `DB_TABLEINFO_ORDERTABLE`
     *
     * Similar to `DB_TABLEINFO_ORDER` but adds more dimensions to the array
     * in which the table names are keys and the field names are sub-keys.
     * This is helpful for queries that join tables which have identical
     * field names.
     *
     *   ```php
     *   [num_fields] => 3
     *   [ordertable] => Array (
     *       [tblFoo] => Array (
     *           [fldId] => 0
     *           [fldPhone] => 1
     *       )
     *       [tblBar] => Array (
     *           [fldId] => 2
     *       )
     *   )
     *   ```
     *
     * The `flags` element contains a space separated list of extra
     * information about the field.  This data is inconsistent between DBMS's
     * due to the way each DBMS works.
     *   + `primary_key`
     *   + `unique_key`
     *   + `multiple_key`
     *   + `not_null`
     *
     * Most DBMS's only provide the `table` and `flags` elements if `$result`
     * is a table name. The following DBMS's provide full information from
     * queries:
     *   + fbsql
     *   + mysql
     *
     * If the 'portability' option has `DB_PORTABILITY_LOWERCASE` turned on,
     * the names of tables and fields will be lowercased.
     *
     * @param object|string  $result  Result object from a query or a
     *                                string containing the name of a table.
     *                                While this also accepts a query result
     *                                resource identifier, this behavior is
     *                                deprecated.
     * @param int  $mode              either unused or one of the tableInfo modes:
     *                                `DB_TABLEINFO_ORDERTABLE`,
     *                                `DB_TABLEINFO_ORDER` or `DB_TABLEINFO_FULL`
     *                                (which does both). These are bitwise, so the
     *                                first two can be combined using `|`.
     * @return array|Error            an associative array with the information requested.
     *                                A Pineapple\DB\Error object on failure.
     *
     * @see Common::setOption()
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function tableInfo($result, $mode = null)
    {
        /**
         * If the driver class has a tableInfo() method, that one
         * overrides this one.  But, if the driver doesn't have one,
         * this method runs and tells users about that fact.
         */
        return $this->raiseError(DB::DB_ERROR_NOT_CAPABLE);
    }

    /**
     * Sets (or unsets) a flag indicating that the next query will be a
     * manipulation query, regardless of the usual self::isManip() heuristics.
     *
     * @param boolean $manip true to set the flag overriding the isManip() behaviour,
     *                       false to clear it and fall back onto isManip()
     */
    public function nextQueryIsManip($manip)
    {
        $this->nextQueryManip = $manip ? true : false;
    }

    /**
     * Tell whether a query is a data manipulation or data definition query
     *
     * Examples of data manipulation queries are INSERT, UPDATE and DELETE.
     * Examples of data definition queries are CREATE, DROP, ALTER, GRANT,
     * REVOKE.
     *
     * @param string $query the query
     * @return boolean      whether $query is a data manipulation query
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
        if (preg_match('/^\s*"?(' . $manips . ')\s+/si', $query)) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve the value used to populate an auto-increment or primary key
     * field by the DBMS.
     *
     * @param string $sequence The name of the sequence (optional, only applies to supported engines)
     * @return string|Error    The auto-insert ID, an error if unsupported
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function lastInsertId($sequence = null)
    {
        return $this->raiseError(DB::DB_ERROR_UNSUPPORTED);
    }

    /**
     * Checks if the given query is a manipulation query. This also takes into
     * account the nextQueryManip flag and sets the lastQueryManip flag
     * (and resets nextQueryManip) according to the result.
     *
     * @param string   The query to check.
     * @return boolean true if the query is a manipulation query, false
     *                 otherwise
     */
    protected function checkManip($query)
    {
        $this->lastQueryManip = $this->nextQueryManip || self::isManip($query);
        $this->nextQueryManip = false;
        return $this->lastQueryManip;
    }

    /**
     * Right-trims all strings in an array
     *
     * @param array $array  the array to be trimmed (passed by reference)
     */
    protected function rtrimArrayValues(&$array)
    {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $array[$key] = rtrim($value);
            }
        }
    }

    /**
     * Converts all null values in an array to empty strings
     *
     * @param array  $array  the array to be de-nullified (passed by reference)
     */
    protected function convertNullArrayValuesToEmpty(&$array)
    {
        foreach ($array as $key => $value) {
            if (is_null($value)) {
                $array[$key] = '';
            }
        }
    }

    /**
     * Change the current database we are working on
     *
     * @param string The name of the database to connect to
     * @return mixed true if the operation worked, Pineapple\DB\Error if it
     *               failed, Pineapple\DB\Error with DB_ERROR_UNSUPPORTED if
     *               the feature is not supported by the driver
     *
     * @see Pineapple\DB\Error
     */
    public function changeDatabase($name)
    {
        return $this->raiseError(DB::DB_ERROR_UNSUPPORTED);
    }
}
