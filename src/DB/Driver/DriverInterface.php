<?php
namespace Pineapple\DB\Driver;

use Pineapple\DB\StatementContainer;

interface DriverInterface
{
    /**
     * Get a Pineapple DB error code from a driver-specific error code,
     * returning a standard 'generic error' if unmappable.
     *
     * @param string $code The driver error code to map to native
     *
     * @return int         The DB::DB_ERROR_* constant
     */
    public function getNativeErrorCode($code);

    /**
     * Disconnects from the database server
     *
     * @return bool  TRUE on success, FALSE on failure
     */
    public function disconnect();

    /**
     * Sends a query to the database server
     *
     * @param string  the SQL query string
     *
     * @return mixed  + a PHP result resrouce for successful SELECT queries
     *                + the DB_OK constant for other successful queries
     *                + a Pineapple\DB\Error object on failure
     */
    public function simpleQuery($query);

    /**
     * Move the internal mysql result pointer to the next available result.
     *
     * @param mixed $result a valid sql result resource/object
     * @return false
     */
    public function nextResult(StatementContainer $result);

    /**
     * Places a row from the result set into the given array
     *
     * Formating of the array and the data therein are configurable.
     * See Pineapple\DB\Result::fetchInto() for more information.
     *
     * This method is not meant to be called directly.  Use
     * Pineapple\DB\Result::fetchInto() instead.  It can't be declared
     * "protected" because Pineapple\DB\Result is a separate object.
     *
     * @param mixed         $result    the query result resource
     * @param array         $arr       the referenced array to put the data in
     * @param int           $fetchmode how the resulting array should be indexed
     * @param int           $rownum    the row number to fetch (0 = first row)
     *
     * @return mixed              DB_OK on success, NULL when the end of a
     *                            result set is reached or on failure
     *
     * @see Pineapple\DB\Result::fetchInto()
     */
    public function fetchInto(StatementContainer $result, &$arr, $fetchmode, $rownum = null);

    /**
     * Deletes the result set and frees the memory occupied by the result set
     *
     * This method is not meant to be called directly.  Use
     * Pineapple\DB\Result::free() instead.  It can't be declared "protected"
     * because Pineapple\DB\Result is a separate object.
     *
     * @param mixed $result         PHP's query result resource
     *
     * @return bool                 TRUE on success, FALSE if $result is invalid
     *
     * @see Pineapple\DB\Result::free()
     */
    public function freeResult(StatementContainer &$result);

    /**
     * Gets the number of columns in a result set
     *
     * This method is not meant to be called directly.  Use
     * Pineapple\DB\Result::numCols() instead.  It can't be declared
     * "protected" because Pineapple\DB\Result is a separate object.
     *
     * @param mixed $result     PHP's query result resource
     *
     * @return int|Error        the number of columns. A Pineapple\DB\Error
     *                          object on failure.
     *
     * @see Pineapple\DB\Result::numCols()
     */
    public function numCols(StatementContainer $result);

    /**
     * Gets the number of rows in a result set
     *
     * This method is not meant to be called directly.  Use
     * Pineapple\DB\Result::numRows() instead.  It can't be declared "protected"
     * because Pineapple\DB\Result is a separate object.
     *
     * @param mixed $result     PHP's query result resource
     *
     * @return int|Error        the number of rows. A Pineapple\DB\Error
     *                          object on failure.
     *
     * @see Pineapple\DB\Result::numRows()
     */
    public function numRows(StatementContainer $result);

    /**
     * Enables or disables automatic commits
     *
     * @param bool $onoff  true turns it on, false turns it off
     *
     * @return int|Error   DB_OK on success. A Pineapple\DB\Error object if
     *                     the driver doesn't support auto-committing
     *                     transactions.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function autoCommit($onoff = false);

    /**
     * Commits the current transaction
     *
     * @return int|Error  DB_OK on success. A Pineapple\DB\Error object on
     *                    failure.
     */
    public function commit();

    /**
     * Reverts the current transaction
     *
     * @return int|Error  DB_OK on success. A Pineapple\DB\Error object on
     *                    failure.
     */
    public function rollback();

    /**
     * Determines the number of rows affected by a data maniuplation query
     *
     * 0 is returned for queries that don't manipulate data.
     *
     * @return int|Error  the number of rows. A Pineapple\DB\Error object
     *                    on failure.
     */
    public function affectedRows();

    /**
     * Quotes a string so it can be safely used as a table or column name
     * (WARNING: using names that require this is a REALLY BAD IDEA)
     *
     * WARNING:  Older versions of MySQL can't handle the backtick
     * character (<kbd>`</kbd>) in table or column names.
     *
     * @param string $str  identifier name to be quoted
     *
     * @return string      quoted identifier string
     *
     * @see Pineapple\DB\Driver\Common::quoteIdentifier()
     */
    public function quoteIdentifier($str);

    /**
     * Escapes a string according to the current DBMS's standards
     *
     * @param string $str   the string to be escaped
     *
     * @return string|Error the escaped string, or an error
     *
     * @see Pineapple\DB\Driver\Common::quoteSmart()
     */
    public function escapeSimple($str);

    /**
     * Gets the DBMS' native error code produced by the last query
     *
     * @return int  the DBMS' error code
     */
    public function errorNative();

    /**
     * Returns information about a table or a result set
     *
     * @param StatementContainer|string $result Pineapple\DB\Result object from a query or a
     *                                          string containing the name of a table.
     *                                          While this also accepts a query result
     *                                          resource identifier, this behavior is
     *                                          deprecated.
     * @param int                       $mode   a valid tableInfo mode
     * @return mixed   an associative array with the information requested.
     *                 A Pineapple\DB\Error object on failure.
     *
     * @see Pineapple\DB\Driver\Common::setOption()
     */
    public function tableInfo($result, $mode = null);

    /**
     * Retrieve the value used to populate an auto-increment or primary key
     * field by the DBMS.
     *
     * @param string $sequence The name of the sequence (optional, only applies to supported engines)
     * @return string|Error    The auto-insert ID, an error if unsupported
     */
    public function lastInsertId($sequence = null);

    /**
     * Returns the value of an option
     *
     * @param string $option  the option name you're curious about
     * @return mixed  the option's value
     */
    public function getOption($option);

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
    public function getFetchMode();

    /**
     * Gets the class used to map rows into objects for DB::DB_FETCHMODE_OBJECT
     *
     * @return string The class used to map rows
     * @see Pineapple\DB\Row
     */
    public function getFetchModeObjectClass();

    /**
     * Gets an advertised feature of the driver
     *
     * @param string $feature Name of the feature to return
     */
    public function getFeature($feature);

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
    public function query($query, $params = []);

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
    );
}
