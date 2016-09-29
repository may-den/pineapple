<?php
namespace Pineapple\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Error;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Statement as DBALStatement;
use Doctrine\DBAL\Exception\DriverException as DBALDriverException;

use PDO;

/**
 * A PEAR DB driver that uses Doctrine's DBAL as an underlying database
 * layer.
 */
class DoctrineDbal extends Common
{
    /**
     * The DB driver type (mysql, oci8, odbc, etc.)
     * @var string
     */
    protected $phptype = 'doctrinedbal';

    /**
     * The database syntax variant to be used (db2, access, etc.), if any
     * @var string
     */
    protected $dbsyntax = 'doctrinedbal';

    /**
     * The capabilities of this DB implementation
     *
     * The 'new_link' element contains the PHP version that first provided
     * new_link support for this DBMS.  Contains false if it's unsupported.
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
        'new_link' => false,
        'numrows' => true,
        'pconnect' => false,
        'prepare' => false,
        'ssl' => true,
        'transactions' => true,
    ];

    /**
     * A mapping of native error codes to DB error codes
     * @var array
     */
    protected $errorcode_map = [
        1004 => DB::DB_ERROR_CANNOT_CREATE,
        1005 => DB::DB_ERROR_CANNOT_CREATE,
        1006 => DB::DB_ERROR_CANNOT_CREATE,
        1007 => DB::DB_ERROR_ALREADY_EXISTS,
        1008 => DB::DB_ERROR_CANNOT_DROP,
        1022 => DB::DB_ERROR_ALREADY_EXISTS,
        1044 => DB::DB_ERROR_ACCESS_VIOLATION,
        1046 => DB::DB_ERROR_NODBSELECTED,
        1048 => DB::DB_ERROR_CONSTRAINT,
        1049 => DB::DB_ERROR_NOSUCHDB,
        1050 => DB::DB_ERROR_ALREADY_EXISTS,
        1051 => DB::DB_ERROR_NOSUCHTABLE,
        1054 => DB::DB_ERROR_NOSUCHFIELD,
        1061 => DB::DB_ERROR_ALREADY_EXISTS,
        1062 => DB::DB_ERROR_ALREADY_EXISTS,
        1064 => DB::DB_ERROR_SYNTAX,
        1091 => DB::DB_ERROR_NOT_FOUND,
        1100 => DB::DB_ERROR_NOT_LOCKED,
        1136 => DB::DB_ERROR_VALUE_COUNT_ON_ROW,
        1142 => DB::DB_ERROR_ACCESS_VIOLATION,
        1146 => DB::DB_ERROR_NOSUCHTABLE,
        1216 => DB::DB_ERROR_CONSTRAINT,
        1217 => DB::DB_ERROR_CONSTRAINT,
        1356 => DB::DB_ERROR_DIVZERO,
        1451 => DB::DB_ERROR_CONSTRAINT,
        1452 => DB::DB_ERROR_CONSTRAINT,
    ];

    // @var DBALConnection Our Doctrine DBAL connection
    protected $connection = null;

    /**
     * A copy of the last pdostatement object
     * @var DBALStatement
     */
    public $lastStatement = null;

    /**
     * Should data manipulation queries be committed automatically?
     * @var bool
     * @access private
     */
    public $autocommit = true;

    /**
     * The quantity of transactions begun
     *
     * {@internal  While this is private, it can't actually be designated
     * private in PHP 5 because it is directly accessed in the test suite.}}
     *
     * @var integer
     * @access private
     */
    public $transaction_opcount = 0;

    /**
     * The database specified in the DSN
     *
     * It's a fix to allow calls to different databases in the same script.
     *
     * @var string
     * @access private
     */
    private $db = '';

    /**
     * Connect to the database server, log in and open the database
     *
     * Don't call this method directly.  Use DB::connect() instead.
     *
     * PEAR DB's mysqli driver supports the following extra DSN options:
     *   + When the 'ssl' $option passed to DB::connect() is true:
     *     + key      The path to the key file.
     *     + cert     The path to the certificate file.
     *     + ca       The path to the certificate authority file.
     *     + capath   The path to a directory that contains trusted SSL
     *                 CA certificates in pem format.
     *     + cipher   The list of allowable ciphers for SSL encryption.
     *
     * Example of how to connect using SSL:
     * <code>
     * require_once 'DB.php';
     *
     * $dsn = [
     *     'phptype' => 'mysqli',
     *     'username' => 'someuser',
     *     'password' => 'apasswd',
     *     'hostspec' => 'localhost',
     *     'database' => 'thedb',
     *     'key' => 'client-key.pem',
     *     'cert' => 'client-cert.pem',
     *     'ca' => 'cacert.pem',
     *     'capath' => '/path/to/ca/dir',
     *     'cipher' => 'AES',
     * ];
     *
     * $options = [
     *     'ssl' => true,
     * ];
     *
     * $db = DB::connect($dsn, $options);
     * if (Util::isError($db)) {
     *     die($db->getMessage());
     * }
     * </code>
     *
     * @param array $dsn         the data source name
     * @param bool  $persistent  should the connection be persistent?
     *
     * @return int|Error         DB_OK on success.
     *                           A Pineapple\DB\Error object on failure.
     */
    public function connect($dsn, $persistent = false)
    {
        // this returns success, but in effect does nothing.
        // since $this->connection remains unset, driver still behaves well.
        return DB::DB_OK;
    }

    /**
     * Set the DBAL connection handle in the object
     *
     * @param DBALConnection   $connection A constructed DBAL connection handle
     * @param array            $dsn        A valid PEAR DB DSN
     * @return DoctrineDbal    The constructed Pineapple\DB\Driver\DoctrineDbal object
     */
    public function setConnectionHandle(DBALConnection $connection, array $dsn = [])
    {
        $this->dsn = $dsn;
        $this->connection = $connection;

        if (isset($dsn['dbsyntax'])) {
            $this->dbsyntax = $dsn['dbsyntax'];
        }

        if (isset($dsn['database'])) {
            $this->db = $dsn['database'];
        }

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
     * Determine if we're connected
     *
     * @return bool true if connected, false if not
     */
    private function connected()
    {
        if (isset($this->connection) && $this->connection) {
            return true;
        }
        return false;
    }

    /**
     * Sends a query to the database server
     *
     * @param string  the SQL query string
     *
     * @return mixed  + a PHP result resrouce for successful SELECT queries
     *                + the DB_OK constant for other successful queries
     *                + a Pineapple\DB\Error object on failure
     */
    public function simpleQuery($query)
    {
        $ismanip = $this->_checkManip($query);
        $this->lastQuery = $query;
        $query = $this->modifyQuery($query);
        if (!$this->connected()) {
            return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
        }
        if (!$this->autocommit && $ismanip) {
            if ($this->transaction_opcount == 0) {
                // dbal doesn't return a status for begin transaction. pdo does.
                $this->connection->beginTransaction();
                // ...so we can't (easily) capture an exception if this goes wrong.
            }
            $this->transaction_opcount++;
        }

        // enable/disable result_buffering in mysql
        // @codeCoverageIgnoreStart
        if ($this->getPlatform() === 'mysql') {
            if (!$this->options['result_buffering']) {
                $this->connection
                    ->getWrappedConnection()
                    ->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            } else {
                $this->connection
                    ->getWrappedConnection()
                    ->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        }
        // @codeCoverageIgnoreEnd

        try {
            $result = $this->connection->query($query);
        } catch (DBALDriverException $exception) {
            return $this->raiseError(DB::DB_ERROR, null, null, $exception->getMessage());
        }

        if (!$ismanip && is_object($result)) {
            $this->lastStatement = $result;
            return $result;
        }

        return DB::DB_OK;
    }

    /**
     * Move the internal mysql result pointer to the next available result.
     *
     * This method has not been implemented yet.
     *
     * @param resource $result a valid sql result resource
     * @return false
     * @access public
     */
    public function nextResult($result)
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
     * @param resource $result    the query result resource
     * @param array    $arr       the referenced array to put the data in
     * @param int      $fetchmode how the resulting array should be indexed
     * @param int      $rownum    the row number to fetch (0 = first row)
     *
     * @return mixed              DB_OK on success, NULL when the end of a
     *                            result set is reached or on failure
     *
     * @see Pineapple\DB\Result::fetchInto()
     */
    public function fetchInto(DBALStatement $result, &$arr, $fetchmode, $rownum = null)
    {
        if ($fetchmode & DB::DB_FETCHMODE_ASSOC) {
            $arr = $result->fetch(PDO::FETCH_ASSOC, null, $rownum);
            if (($this->options['portability'] & DB::DB_PORTABILITY_LOWERCASE) && $arr) {
                $arr = array_change_key_case($arr, CASE_LOWER);
            }
        } else {
            $arr = $result->fetch(PDO::FETCH_NUM);
        }

        if (!$arr) {
            return null;
        }

        if ($this->options['portability'] & DB::DB_PORTABILITY_RTRIM) {
            $this->_rtrimArrayValues($arr);
        }

        if ($this->options['portability'] & DB::DB_PORTABILITY_NULL_TO_EMPTY) {
            $this->_convertNullArrayValuesToEmpty($arr);
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
    public function freeResult(DBALStatement &$result = null)
    {
        if ($result === null) {
            return false;
        }
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
    public function numCols($result)
    {
        $cols = $result->columnCount();
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
     */
    public function numRows($result)
    {
        $rows = $result->rowCount();
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
        // XXX if $this->transaction_opcount > 0, we should probably
        // issue a warning here.
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
        if ($this->transaction_opcount > 0) {
            if (!$this->connection) {
                return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
            }
            $result = $this->connection->commit();
            $this->transaction_opcount = 0;
            if (!$result) {
                return $this->myRaiseError();
            }
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
        if ($this->transaction_opcount > 0) {
            if (!$this->connection) {
                return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
            }
            $result = $this->connection->rollback();
            $this->transaction_opcount = 0;
            if (!$result) {
                return $this->myRaiseError();
            }
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
        if ($this->lastStatement === null || !is_object($this->lastStatement)) {
            $this->myRaiseError();
        }

        if ($this->lastQueryManip) {
            return $this->lastStatement->rowCount();
        } else {
            return 0;
        }
    }

    /**
     * Returns the next free id in a sequence
     *
     * @param string  $seqName  name of the sequence
     * @param boolean $onDemand when true, the seqence is automatically
     *                          created if it does not exist
     *
     * @return int|Error  the next id number in the sequence.
     *                    A Pineapple\DB\Error object on failure.
     *
     * @see Pineapple\DB\Driver\Common::nextID(),
     *      Pineapple\DB\Driver\Common::getSequenceName(),
     *      Pineapple\DB\Driver\DoctrineDbal::createSequence(),
     *      Pineapple\DB\Driver\DoctrineDbal::dropSequence()
     */
    public function nextId($seqName, $onDemand = true)
    {
        $seqName = $this->getSequenceName($seqName);
        do {
            $repeat = 0;
            $result = $this->query("UPDATE {$seqName} SET id = LAST_INSERT_ID(id + 1)");
            if ($result === DB::DB_OK) {
                // COMMON CASE
                $id = $this->connection->lastInsertId();
                if ($id != 0) {
                    return $id;
                }

                // EMPTY SEQ TABLE
                // Sequence table must be empty for some reason,
                // so fill it and return 1
                // Obtain a user-level lock
                $result = $this->getOne("SELECT GET_LOCK('${seqName}_lock', 10)");
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }
                if ($result == 0) {
                    return $this->myRaiseError(DB::DB_ERROR_NOT_LOCKED);
                }

                // add the default value
                $result = $this->query("REPLACE INTO {$seqName} (id) VALUES (0)");
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }

                // Release the lock
                $result = $this->getOne("SELECT RELEASE_LOCK('${seqName}_lock')");
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }
                // We know what the result will be, so no need to try again
                return 1;
            } elseif ($onDemand && DB::isError($result) &&
                $result->getCode() == DB::DB_ERROR_NOSUCHTABLE) {
                // ONDEMAND TABLE CREATION
                $result = $this->createSequence($seqName);

                // Since createSequence initializes the ID to be 1,
                // we do not need to retrieve the ID again (or we will get 2)
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                } else {
                    // First ID of a newly created sequence is 1
                    return 1;
                }
            } elseif (DB::isError($result) &&
                      $result->getCode() == DB::DB_ERROR_ALREADY_EXISTS) {
                // BACKWARDS COMPAT
                // see BCsequence() comment
                $result = $this->BCsequence($seqName);
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }
                $repeat = 1;
            }
        } while ($repeat);

        return $this->raiseError($result);
    }

    /**
     * Creates a new sequence
     *
     * @param string $seqName  name of the new sequence
     *
     * @return int|Error       DB_OK on success. A Pineapple\DB\Error
     *                         object on failure.
     *
     * @see Pineapple\DB\Driver\Common::createSequence(),
     *      Pineapple\DB\Driver\Common::getSequenceName(),
     *      Pineapple\DB\Driver\Pineapple\DB\Driver\DoctrineDbal::nextID(),
     *      Pineapple\DB\Driver\Pineapple\DB\Driver\DoctrineDbal::dropSequence()
     */
    public function createSequence($seqName)
    {
        $seqName = $this->getSequenceName($seqName);
        $res = $this->query("CREATE TABLE {$seqName} (id INTEGER UNSIGNED AUTO_INCREMENT NOT NULL, PRIMARY KEY(id))");
        if (DB::isError($res)) {
            return $res;
        }
        // insert yields value 1, nextId call will generate ID 2
        return $this->query("INSERT INTO ${seqName} (id) VALUES (0)");
    }

    /**
     * Deletes a sequence
     *
     * @param string $seqName  name of the sequence to be deleted
     *
     * @return int|Error       DB_OK on success. A Pineapple\DB\Error object
     *                         on failure.
     *
     * @see Pineapple\DB\Driver\Common::dropSequence(),
     *      Pineapple\DB\Driver\Common::getSequenceName(),
     *      Pineapple\DB\Driver\Pineapple\DB\Driver\DoctrineDbal::nextID(),
     *      Pineapple\DB\Driver\Pineapple\DB\Driver\DoctrineDbal::createSequence()
     */
    public function dropSequence($seqName)
    {
        return $this->query('DROP TABLE ' . $this->getSequenceName($seqName));
    }

    /**
     * Backwards compatibility with old sequence emulation implementation
     * (clean up the dupes)
     *
     * @param string $seqName  the sequence name to clean up
     *
     * @return bool|Error      true on success. A Pineapple\DB\Error object
     *                         on failure.
     *
     * @access private
     */
    private function BCsequence($seqName)
    {
        // Obtain a user-level lock... this will release any previous
        // application locks, but unlike LOCK TABLES, it does not abort
        // the current transaction and is much less frequently used.
        $result = $this->getOne("SELECT GET_LOCK('${seqName}_lock',10)");
        if (DB::isError($result)) {
            return $result;
        }
        if ($result == 0) {
            // Failed to get the lock, can't do the conversion, bail
            // with a DB_ERROR_NOT_LOCKED error
            return $this->myRaiseError(DB::DB_ERROR_NOT_LOCKED);
        }

        $highest_id = $this->getOne("SELECT MAX(id) FROM ${seqName}");
        if (DB::isError($highest_id)) {
            return $highest_id;
        }

        // This should kill all rows except the highest
        // We should probably do something if $highest_id isn't
        // numeric, but I'm at a loss as how to handle that...
        $result = $this->query("DELETE FROM {$seqName} WHERE id <> $highest_id");
        if (DB::isError($result)) {
            return $result;
        }

        // If another thread has been waiting for this lock,
        // it will go thru the above procedure, but will have no
        // real effect
        $result = $this->getOne("SELECT RELEASE_LOCK('${seqName}_lock')");
        if (DB::isError($result)) {
            return $result;
        }
        return true;
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
     * @param string $str  the string to be escaped
     *
     * @return string      the escaped string
     *
     * @see Pineapple\DB\Driver\Common::quoteSmart()
     * @since Method available since Release 1.6.0
     */
    public function escapeSimple($str)
    {
        return @preg_replace('/^(.)(.*)\g1$/', '$2', $this->connection->quote($str));
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
    public function modifyLimitQuery($query, $from, $count, $params = [])
    {
        if (DB::isManip($query) || $this->nextQueryManip) {
            return $query . " LIMIT $count";
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
    public function myRaiseError($errno = null)
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
            // Fix for bug #11580.
            if (!$this->connection) {
                return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
            }

            /**
             * Probably received a table name.
             * Create a result resource identifier.
             */
            $id = $this->simpleQuery("SELECT * FROM $result LIMIT 0");
            $got_string = true;
        } elseif (is_object($result)) {
            /**
             * Probably received a result object.
             * Extract the result resource identifier.
             */
            $id = $result->result;
            $got_string = false;
        } else {
            $this->myRaiseError();
        }

        if (!is_object($id) || !($id instanceof DBALStatement)) {
            return $this->myRaiseError(DB::DB_ERROR_NEED_MORE_DATA);
        }

        if ($this->options['portability'] & DB::DB_PORTABILITY_LOWERCASE) {
            $case_func = 'strtolower';
        } else {
            $case_func = 'strval';
        }

        $count = $id->columnCount();
        $res = [];

        if ($mode) {
            $res['num_fields'] = $count;
        }

        for ($i = 0; $i < $count; $i++) {
            $tmp = $id->getColumnMeta($i);

            $res[$i] = [
                'table' => $tmp['table'],
                'name' => $tmp['name'],
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
     * Obtains the query string needed for listing a given type of objects
     *
     * @param string $type  the kind of objects you want to retrieve
     *
     * @return string|null  the SQL query string or null if the driver
     *                      doesn't support the object type requested
     *
     * @access protected
     * @todo these are mysql specific, and this is a doctrine driver
     * @see Pineapple\DB\Driver\Common::getListOf()
     */
    protected function getSpecialQuery($type)
    {
        switch ($type) {
            case 'tables':
                return 'SHOW TABLES';
            case 'users':
                return 'SELECT DISTINCT User FROM mysql.user';
            case 'databases':
                return 'SHOW DATABASES';
            default:
                return null;
        }
    }

    /**
     * Obtain a "rationalised" database major name
     *
     * @return string Lower-cased type of database, e.g. "mysql", "pgsql"
     */
    private function getPlatform()
    {
        if (!$this->connected()) {
            return $this->raiseError(DB::DB_ERROR_NODBSELECTED);
        }

        switch ($this->connection->getDatabasePlatform()) {
            case 'MysqlPlatform':
            case 'MySQL57Platform':
                return 'mysql';
                break;

            case 'PostgreSqlPlatform':
            case 'PostgreSQL91Platform':
            case 'PostgreSQL92Platform':
            case 'PostgreSQL94Platfor':
                return 'pgsql';
                break;

            case 'SqlitePlatform':
                return 'sqlite';
                break;

            default:
                return 'unknown';
                break;
        }
    }
}
