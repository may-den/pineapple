<?php
namespace Pineapple\DB\Driver;

use Pineapple\DB\StatementContainer;

interface DriverInterface
{
    /**
     * Constructor (only calls the constructor within `Util` with the DB\Error class)
     */
    public function __construct();

    /**
     * Retrieve the last query string
     *
     * @return string
     */
    public function getLastQuery();

    /**
     * Retrieve the last set of parameters used in a query
     *
     * @return array
     */
    public function getLastParameters();

    /**
     * Gets an advertised feature of the driver
     *
     * @param string $feature Name of the feature to return
     */
    public function getFeature($feature);

    /**
     * Accept that your UPDATE without a WHERE is going to update a lot of
     * data and that you understand the consequences.
     *
     * @param boolean $flag true to make UPDATE without WHERE work
     * @since Method available since Pineapple 0.1.0
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function setAcceptConsequencesOfPoorCodingChoices($flag = false);

    /**
     * Formats input so it can be safely used in a query
     *
     * The output depends on the PHP data type of input and the database
     * type being used.
     *
     * @param mixed $property the data to be formatted
     *
     * @return mixed          the formatted data.
     *
     * The format depends on the input's PHP type:
     *   -   `input` -> `returns`
     *   -   `null` -> the string `NULL`
     *   -   `integer` or `double` -> the unquoted number
     *   -   `bool` -> output depends on the driver in use
     *                 Most drivers return integers: `1` if
     *                 `true` or `0` if
     *                 `false`.
     *                 Some return strings: `TRUE` if
     *                 `true` or `FALSE` if
     *                 `false`.
     *                 Finally one returns strings: `T` if
     *                 `true` or `F` if
     *                 `false`. Here is a list of each DBMS,
     *                 the values returned and the suggested column type:
     *      -   `dbase` -> `T/F`        (`Logical`)
     *      -   `fbase` -> `TRUE/FALSE` (`BOOLEAN`)
     *      -   `ibase` -> `1/0`        (`SMALLINT`) [1]
     *      -   `ifx` -> `1/0`          (`SMALLINT`) [1]
     *      -   `msql` -> `1/0`         (`INTEGER`)
     *      -   `mssql` -> `1/0`        (`BIT`)
     *      -   `mysql` -> `1/0`        (`TINYINT(1)`)
     *      -   `mysqli` -> `1/0`       (`TINYINT(1)`)
     *      -   `oci8` -> `1/0`         (`NUMBER(1)`)
     *      -   `odbc` -> `1/0`         (`SMALLINT`) [1]
     *      -   `pgsql` -> `TRUE/FALSE` (`BOOLEAN`)
     *      -   `sqlite` -> `1/0`       (`INTEGER`)
     *      -   `sybase` -> `1/0`       (`TINYINT(1)`)
     *
     *    [1] Accommodate the lowest common denominator because not all
     *    versions of have `BOOLEAN`.
     *
     *    other (including strings and numeric strings) ->
     *    the data with single quotes escaped by preceeding
     *    single quotes, backslashes are escaped by preceeding
     *    backslashes, then the whole string is encapsulated
     *    between single quotes
     *
     * @see Common::escapeSimple()
     * @since Method available since Release 1.6.0
     */
    public function quoteSmart($property);

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
    public function escapeSimple($str);

    /**
     * Tells whether the present driver supports a given feature
     *
     * @param string $feature  the feature you're curious about
     *
     * @return mixed Usually boolean, whether this driver supports $feature,
     *               in the case of limit a string
     */
    public function provides($feature);

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
    public function setFetchMode($fetchmode, $objectClass = stdClass::class);

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
     * Get a Pineapple DB error code from a driver-specific error code,
     * returning a standard 'generic error' if unmappable.
     *
     * @param string $code The driver error code to map to native
     *
     * @return int         The DB::DB_ERROR_* constant
     */
    public function getNativeErrorCode($code);

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
    public function setOption($option, $value);

    /**
     * Returns the value of an option
     *
     * @param string $option  the option name you're curious about
     * @return mixed  the option's value
     */
    public function getOption($option);

    /**
     * Determine if we're connected
     *
     * @return boolean true if connected, false if not
     */
    public function connected();

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
    public function prepare($query);

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
     */
    public function autoPrepare($table, $tableFields, $mode = DB::DB_AUTOQUERY_INSERT, $where = null);

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
     */
    public function autoExecute($table, $fieldsValues, $mode = DB::DB_AUTOQUERY_INSERT, $where = null);

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
    public function buildManipSQL($table, $tableFields, $mode, $where = null);

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
     */
    public function execute($stmt, $data = []);

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
     */
    public function executeMultiple($stmt, array $data);

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
     */
    public function freePrepared($stmt, $freeResource = true);

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
     */
    public function query($query, $params = []);

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
     * Quotes a string so it can be safely used as a table or column name
     * (WARNING: using names that require this is a REALLY BAD IDEA)
     *
     * Delimiting style depends on which database driver is being used.
     *
     * NOTE: just because you CAN use delimited identifiers doesn't mean
     * you SHOULD use them.  In general, they end up causing way more
     * problems than they solve.
     *
     * Portability is broken by using the following characters inside
     * delimited identifiers:
     *   + backtick (```) -- due to MySQL
     *   + double quote (`"`) -- due to Oracle
     *   + brackets (`[` or `]`) -- due to Access
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
    public function quoteIdentifier($str);

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
     */
    public function limitQuery($query, $from, $count, $params = []);

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
     */
    public function getOne($query, $params = []);

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
     */
    public function getRow($query, $params = [], $fetchmode = DB::DB_FETCHMODE_DEFAULT);

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
     */
    public function getCol($query, $col = 0, $params = []);

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
     */
    public function getAssoc(
        $query,
        $forceArray = false,
        $params = [],
        $fetchmode = DB::DB_FETCHMODE_DEFAULT,
        $group = false
    );

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
     */
    public function getAll($query, $params = [], $fetchmode = DB::DB_FETCHMODE_DEFAULT);

    /**
     * Enables or disables automatic commits
     *
     * @param bool $onoff true turns it on, false turns it off
     * @return int|Error  DB_OK on success. A Pineapple\DB\Error object if
     *                    the driver doesn't support auto-committing transactions.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function autoCommit($onoff = false);

    /**
     * Commits the current transaction
     *
     * @return int|Error DB_OK on success. A Pineapple\DB\Error object on failure.
     */
    public function commit();

    /**
     * Reverts the current transaction
     *
     * @return int|Error DB_OK on success. A Pineapple\DB\Error object on failure.
     */
    public function rollback();

    /**
     * Determines the number of rows in a query result
     *
     * @param StatementContainer $result the query result idenifier produced by PHP
     * @return int|Error                 the number of rows.  A Pineapple\DB\Error object on failure.
     */
    public function numRows(StatementContainer $result);

    /**
     * Determines the number of rows affected by a data maniuplation query
     *
     * 0 is returned for queries that don't manipulate data.
     *
     * @return int|Error  the number of rows.  A Pineapple\DB\Error object on failure.
     */
    public function affectedRows();

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

    /**
     * Gets the DBMS' native error code produced by the last query
     *
     * @return mixed  the DBMS' error code.  A Pineapple\DB\Error object on failure.
     */
    public function errorNative();

    /**
     * Maps native error codes to DB's portable ones
     *
     * @param string|int $nativecode the error code returned by the DBMS
     * @return int                   the portable DB error code.  Return DB_ERROR if the
     *                               current driver doesn't have a mapping for the
     *                               $nativecode submitted.
     */
    public function errorCode($nativecode);

    /**
     * Maps a DB error code to a textual message
     *
     * @param int $dbcode the DB error code
     * @return string     the error message corresponding to the error code
     *                    submitted.  FALSE if the error code is unknown.
     *
     * @see DB::errorMessage()
     */
    public function errorMessage($dbcode);

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
     */
    public function tableInfo($result, $mode = null);

    /**
     * Sets (or unsets) a flag indicating that the next query will be a
     * manipulation query, regardless of the usual self::isManip() heuristics.
     *
     * @param boolean $manip true to set the flag overriding the isManip() behaviour,
     *                       false to clear it and fall back onto isManip()
     */
    public function nextQueryIsManip($manip);

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
    public static function isManip($query);

    /**
     * Retrieve the value used to populate an auto-increment or primary key
     * field by the DBMS.
     *
     * @param string $sequence The name of the sequence (optional, only applies to supported engines)
     * @return string|Error    The auto-insert ID, an error if unsupported
     */
    public function lastInsertId($sequence = null);

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
    public function changeDatabase($name);
}
