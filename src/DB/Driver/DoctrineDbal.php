<?php
namespace Pineapple\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Error;
use Pineapple\DB\Driver\DriverInterface;
use Pineapple\DB\StatementContainer;
use Pineapple\DB\Exception\DriverException;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Statement as DBALStatement;
use Doctrine\DBAL\Exception\DriverException as DBALDriverException;
use Doctrine\DBAL\ConnectionException as DBALConnectionException;

use PDO;
use PDOException;

/**
 * A PEAR DB driver that uses Doctrine's DBAL as an underlying database
 * layer.
 */
class DoctrineDbal extends Common implements DriverInterface
{
    use Components\AnsiSqlErrorCodes;

    /**
     * The capabilities of this DB implementation
     *
     * Meaning of the 'limit' element:
     *   + 'emulate' = emulate with fetch row by number
     *   + 'alter'   = alter the query
     *   + false     = skip rows
     *
     * @var array
     */
    protected $features = [
        'limit' => 'alter',
        'numrows' => true,
        'prepare' => false,
        'transactions' => true,
    ];

    // @var DBALConnection Our Doctrine DBAL connection
    protected $connection = null;

    /**
     * A copy of the last pdostatement object
     * @var DBALStatement
     */
    private $lastStatement = null;

    /**
     * Should data manipulation queries be committed automatically?
     * @var bool
     * @access protected
     */
    protected $autocommit = true;

    /**
     * The quantity of transactions begun
     *
     * @var integer
     * @access private
     */
    private $transactionOpcount = 0;

    /**
     * Set the DBAL connection handle in the object
     *
     * @param DBALConnection   $connection A constructed DBAL connection handle
     * @return DoctrineDbal    The constructed Pineapple\DB\Driver\DoctrineDbal object
     */
    public function setConnectionHandle(DBALConnection $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Disconnects from the database server
     *
     * @return bool  TRUE on success, FALSE on failure
     */
    public function disconnect()
    {
        unset($this->connection);
        return true;
    }

    /**
     * Sends a query to the database server
     *
     * @param string $query the SQL query string
     *
     * @return mixed        a StatementContainer object for successful SELECT
     *                      queries the DB_OK constant for other successful
     *                      queries a Pineapple\DB\Error object on failure.
     */
    public function simpleQuery($query)
    {
        $ismanip = $this->checkManip($query);
        $this->lastQuery = $query;
        $query = $this->modifyQuery($query);

        if (!$this->connected()) {
            return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
        }

        if (!$this->autocommit && $ismanip) {
            if ($this->transactionOpcount == 0) {
                // dbal doesn't return a status for begin transaction. pdo does. still, exceptions.
                try {
                    $this->connection->beginTransaction();
                    // sqlite supports transactions so this can't be tested right now
                    // @codeCoverageIgnoreStart
                } catch (PDOException $transactionException) {
                    return $this->raiseError(DB::DB_ERROR, null, null, $transactionException->getMessage());
                    // @codeCoverageIgnoreEnd
                }
            }
            $this->transactionOpcount++;
        }

        // @todo this needs setting on the prepare() driver options, which doctrine doesn't support
        // @codeCoverageIgnoreStart
        if (($this->getPlatform() === 'mysql') && !$this->options['result_buffering']) {
            return $this->raiseError(DB::DB_ERROR_UNSUPPORTED);
        }
        // @codeCoverageIgnoreEnd

        try {
            $statement = $this->connection->query($query);
        } catch (DBALDriverException $exception) {
            return $this->raiseError(DB::DB_ERROR, null, null, $exception->getMessage());
        }

        // keep this so we can perform rowCount and suchlike later
        $this->lastStatement = $statement;

        // fetch queries should return the result object now
        if (!$ismanip && isset($statement) && ($statement instanceof DBALStatement)) {
            return new StatementContainer($statement);
        }

        // ...whilst insert/update/delete just gets a "sure, it went well" result
        return DB::DB_OK;
    }

    /**
     * Move the internal mysql result pointer to the next available result.
     *
     * This method has not been implemented yet.
     *
     * @return false
     * @access public
     */
    public function nextResult(StatementContainer $result)
    {
        return false;
    }

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
     * @param DBALStatement $result    the query result resource
     * @param array         $arr       the referenced array to put the data in
     * @param int           $fetchmode how the resulting array should be indexed
     * @param int           $rownum    the row number to fetch (0 = first row)
     *
     * @return mixed              DB_OK on success, NULL when the end of a
     *                            result set is reached or on failure
     *
     * @see Pineapple\DB\Result::fetchInto()
     */
    public function fetchInto(StatementContainer $result, &$arr, $fetchmode, $rownum = null)
    {
        if ($fetchmode & DB::DB_FETCHMODE_ASSOC) {
            $arr = self::getStatement($result)->fetch(PDO::FETCH_ASSOC, null, $rownum);
            if (($this->options['portability'] & DB::DB_PORTABILITY_LOWERCASE) && $arr) {
                $arr = array_change_key_case($arr, CASE_LOWER);
            }
        } else {
            try {
                $arr = self::getStatement($result)->fetch(PDO::FETCH_NUM);
            } catch (DriverException $fetchException) {
                return $this->raiseError(DB::DB_ERROR, null, null, $fetchException->getMessage());
            }
        }

        if (!$arr) {
            return null;
        }

        if ($this->options['portability'] & DB::DB_PORTABILITY_RTRIM) {
            $this->rtrimArrayValues($arr);
        }

        if ($this->options['portability'] & DB::DB_PORTABILITY_NULL_TO_EMPTY) {
            $this->convertNullArrayValuesToEmpty($arr);
        }
        return DB::DB_OK;
    }

    /**
     * Deletes the result set and frees the memory occupied by the result set
     *
     * This method is not meant to be called directly.  Use
     * Pineapple\DB\Result::free() instead.  It can't be declared "protected"
     * because Pineapple\DB\Result is a separate object.
     *
     * @param DBALStatement $result PHP's query result resource
     *
     * @return bool                 TRUE on success, FALSE if $result is invalid
     *
     * @see Pineapple\DB\Result::free()
     */
    public function freeResult(StatementContainer &$result)
    {
        $result->freeStatement();
        unset($result);
        return true;
    }

    /**
     * Gets the number of columns in a result set
     *
     * This method is not meant to be called directly.  Use
     * Pineapple\DB\Result::numCols() instead.  It can't be declared
     * "protected" because Pineapple\DB\Result is a separate object.
     *
     * @param resource $result  PHP's query result resource
     *
     * @return int|Error        the number of columns. A Pineapple\DB\Error
     *                          object on failure.
     *
     * @see Pineapple\DB\Result::numCols()
     */
    public function numCols(StatementContainer $result)
    {
        $cols = self::getStatement($result)->columnCount();
        if (!$cols) {
            return $this->myRaiseError();
        }
        return $cols;
    }

    /**
     * Gets the number of rows in a result set
     *
     * This method is not meant to be called directly.  Use
     * Pineapple\DB\Result::numRows() instead.  It can't be declared "protected"
     * because Pineapple\DB\Result is a separate object.
     *
     * @param resource $result  PHP's query result resource
     *
     * @return int|Error        the number of rows. A Pineapple\DB\Error
     *                          object on failure.
     *
     * @see Pineapple\DB\Result::numRows()
     * @todo This is not easily testable, since not all drivers support this for SELECTs
     * @codeCoverageIgnore
     */
    public function numRows(StatementContainer $result)
    {
        $rows = self::getStatement($result)->rowCount();
        if ($rows === null) {
            return $this->myRaiseError();
        }
        return $rows;
    }

    /**
     * Enables or disables automatic commits
     *
     * @param bool $onoff  true turns it on, false turns it off
     *
     * @return int|Error   DB_OK on success. A Pineapple\DB\Error object if
     *                     the driver doesn't support auto-committing
     *                     transactions.
     */
    public function autoCommit($onoff = false)
    {
        if ($this->options['strict_transactions'] && ($this->transactionOpcount > 0)) {
            return $this->raiseError(DB::DB_ERROR_ACTIVE_TRANSACTIONS);
        }
        $this->autocommit = $onoff ? true : false;
        return DB::DB_OK;
    }

    /**
     * Commits the current transaction
     *
     * @return int|Error  DB_OK on success. A Pineapple\DB\Error object on
     *                    failure.
     */
    public function commit()
    {
        if ($this->transactionOpcount > 0) {
            if (!$this->connected()) {
                return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
            }

            try {
                $this->connection->commit();
                // @todo honestly, i don't know how to generate a failed transaction commit
                // @codeCoverageIgnoreStart
            } catch (DBALConnectionException $e) {
                return $this->myRaiseError();
                // @codeCoverageIgnoreEnd
            }

            $this->transactionOpcount = 0;
        }
        return DB::DB_OK;
    }

    /**
     * Reverts the current transaction
     *
     * @return int|Error  DB_OK on success. A Pineapple\DB\Error object on
     *                    failure.
     */
    public function rollback()
    {
        if ($this->transactionOpcount > 0) {
            if (!$this->connected()) {
                return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
            }

            try {
                $this->connection->rollBack();
                // @todo honestly, i don't know how to generate a failed tranascation rollback
                // @codeCoverageIgnoreStart
            } catch (DBALConnectionException $e) {
                return $this->myRaiseError();
                // @codeCoverageIgnoreEnd
            }

            $this->transactionOpcount = 0;
        }
        return DB::DB_OK;
    }

    /**
     * Determines the number of rows affected by a data maniuplation query
     *
     * 0 is returned for queries that don't manipulate data.
     *
     * @return int|Error  the number of rows. A Pineapple\DB\Error object
     *                    on failure.
     */
    public function affectedRows()
    {
        if (!isset($this->lastStatement) || !($this->lastStatement instanceof DBALStatement)) {
            return $this->myRaiseError();
        }

        if ($this->lastQueryManip) {
            return $this->lastStatement->rowCount();
        }

        return 0;
    }

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
     * @since Method available since Release 1.6.0
     */
    public function quoteIdentifier($str)
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    /**
     * Escapes a string according to the current DBMS's standards
     *
     * @param string $str   the string to be escaped
     *
     * @return string|Error the escaped string, or an error
     *
     * @see Pineapple\DB\Driver\Common::quoteSmart()
     * @since Method available since Release 1.6.0
     */
    public function escapeSimple($str)
    {
        /**
         * this requires something of an explanation.
         * DB was not built for PDO, but the database functions for each driver
         * it supported. this used, for example, mysql_real_escape_string,
         * pgsql_escape_string/pgsql_escape_literal, but the product would not
         * be encapsulated in quotes.
         *
         * Dbal, providing a consistent PDO-like interface (save for a few
         * return values and methods), offers quote(), which _does_ quote the
         * string. but code that uses escapeSimple() expects an escaped string,
         * not a quoted escaped string.
         *
         * we're going to need to strip these quotes. a rundown of the styles:
         * mysql: single quotes, backslash to literal single quotes to escape
         * pgsql: single quotes, literal single quotes are repeated twice
         * sqlite: as pgsql
         *
         * because we're tailored for Dbal, we are (supposedly) agnostic to
         * these things. generically, we could look for either " or ' at the
         * beginning and end, and strip those. as a safety net, check length.
         * we could also just strip single quotes.
         */
        switch ($this->getPlatform()) {
            case 'mysql':
            case 'pgsql':
            case 'sqlite':
                $quotedString = $this->connection->quote($str);

                if ($quotedString === false) {
                    // @codeCoverageIgnoreStart
                    // quoting is supported in sqlite so we'll skip testing for it
                    return $this->raiseError(DB::DB_ERROR_UNSUPPORTED);
                    // @codeCoverageIgnoreEnd
                }

                if (preg_match('/^(["\']).*\g1$/', $quotedString) && ((strlen($quotedString) - strlen($str)) >= 2)) {
                    // it's a quoted string, it's 2 or more characters longer, let's strip
                    return preg_replace('/^(["\'])(.*)\g1$/', '$2', $quotedString);
                }

                // no quotes detected or length is insufficiently different to incorporate quotes
                // n.b. the default mode is PDO::PARAM_STR, typically this won't actually be hit.
                // @codeCoverageIgnoreStart
                return $quotedString;
                break;
                // @codeCoverageIgnoreEnd

            // not going to try covering this
            // @codeCoverageIgnoreStart
            default:
                return $this->raiseError(DB::DB_ERROR_UNSUPPORTED);
                break;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Adds LIMIT clauses to a query string according to current DBMS standards
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
     * @access protected
     */
    protected function modifyLimitQuery($query, $from, $count, $params = [])
    {
        if (self::isManip($query) || $this->nextQueryManip) {
            // THIS MAKES LITERALLY NO SENSE BUT I AM RETAINING FOR COMPATIBILITY.
            // COMPATIBILITY WHICH LIKELY ISN'T NEEDED.
            // @codeCoverageIgnoreStart
            return $query . " LIMIT $count";
            // @codeCoverageIgnoreEnd
        } else {
            return $query . " LIMIT $from, $count";
        }
    }

    /**
     * Produces a Pineapple\DB\Error object regarding the current problem
     *
     * @param int $errno  if the error is being manually raised pass a
     *                    DB_ERROR* constant here.  If this isn't passed
     *                    the error information gathered from the DBMS.
     *
     * @return object  the Pineapple\DB\Error object
     *
     * @see Pineapple\DB\Driver\Common::raiseError(),
     *      Pineapple\DB\Driver\DoctrineDbal::errorNative(), Pineapple\DB\Driver\Common::errorCode()
     */
    private function myRaiseError($errno = null)
    {
        if ($this->connected()) {
            $error = $this->connection->errorInfo();
        } else {
            $error = ['Disconnected', null, 'No active connection'];
        }
        return $this->raiseError(
            $errno,
            null,
            null,
            null,
            $error[0] . ' ** ' . $error[2]
        );
    }

    /**
     * Gets the DBMS' native error code produced by the last query
     *
     * @return int  the DBMS' error code
     */
    public function errorNative()
    {
        return $this->connection->errorCode();
    }

    /**
     * Returns information about a table or a result set
     *
     * @param DBALStatement|string $result Pineapple\DB\Result object from a query or a
     *                                     string containing the name of a table.
     *                                     While this also accepts a query result
     *                                     resource identifier, this behavior is
     *                                     deprecated.
     * @param int                  $mode   a valid tableInfo mode
     *
     * @return mixed   an associative array with the information requested.
     *                 A Pineapple\DB\Error object on failure.
     *
     * @see Pineapple\DB\Driver\Common::setOption()
     */
    public function tableInfo($result, $mode = null)
    {
        if (is_string($result)) {
            if (!$this->connected()) {
                return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
            }

            /**
             * Probably received a table name.
             * Create a result resource identifier.
             * n.b. Retained for compatibility, but this is untestable with sqlite
             */
            // @codeCoverageIgnoreStart
            $tableHandle = $this->simpleQuery("SELECT * FROM $result LIMIT 0");
            // @codeCoverageIgnoreEnd
        } elseif (is_object($result) && isset($result->result)) {
            /**
             * Probably received a result object.
             * Extract the result resource identifier.
             */
            $tableHandle = self::getStatement($result->result);
        } else {
            return $this->myRaiseError();
        }

        if (!is_object($tableHandle) || !($tableHandle instanceof DBALStatement)) {
            // not easy to test without triggering a very difficult error
            // @codeCoverageIgnoreStart
            return $this->myRaiseError(DB::DB_ERROR_NEED_MORE_DATA);
            // @codeCoverageIgnoreEnd
        }

        if ($this->options['portability'] & DB::DB_PORTABILITY_LOWERCASE) {
            $caseFunc = 'strtolower';
        } else {
            $caseFunc = 'strval';
        }

        $count = $tableHandle->columnCount();
        $res = [];

        if ($mode) {
            $res['num_fields'] = $count;
        }

        for ($i = 0; $i < $count; $i++) {
            $tmp = $tableHandle->getColumnMeta($i);

            $res[$i] = [
                'table' => $caseFunc($tmp['table']),
                'name' => $caseFunc($tmp['name']),
                'type' => isset($tmp['native_type']) ? $tmp['native_type'] : 'unknown',
                'len' => $tmp['len'],
                'flags' => $tmp['flags'],
            ];

            if ($mode & DB::DB_TABLEINFO_ORDER) {
                $res['order'][$res[$i]['name']] = $i;
            }
            if ($mode & DB::DB_TABLEINFO_ORDERTABLE) {
                $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
            }
        }

        return $res;
    }

    /**
     * Obtain a "rationalised" database major name
     * n.b. we won't test this, in future it would make sense to see the purpose removed.
     *
     * @return string Lower-cased type of database, e.g. "mysql", "pgsql"
     * @codeCoverageIgnore
     */
    private function getPlatform()
    {
        if (!$this->connected()) {
            return $this->raiseError(DB::DB_ERROR_NODBSELECTED);
        }

        // we're not going to support everything
        switch ($name = $this->connection->getDatabasePlatform()->getName()) {
            case 'mysql':
            case 'sqlite':
                // verbatim name
                return $name;
                break;

            case 'postgresql':
                return 'pgsql'; // this shortened name is intentional
                break;

            default:
                return 'unknown';
                break;
        }
    }

    /**
     * Ensure the result is a valid type for our driver, and return the
     * statement object after a check.
     *
     * @param StatementContainer $result A statement container with a PDOStatement with in it.
     * @return PDOStatement
     */
    private static function getStatement(StatementContainer $result)
    {
        if (is_a($result->getStatement(), DBALStatement::class)) {
            return $result->getStatement();
        }
        throw new DriverException(
            'Expected ' . StatementContainer::class . ' to contain \'' . DBALStatement::class .
                '\', got ' . json_encode($result->getStatementType())
        );
    }
}
