<?php
/**
 * A PEAR DB driver for PHP's PDO::mysql extension
 */

/**
 * Obtain the DB_common class so it can be extended from
 */
require_once 'DB/common.php';

/**
 * A PEAR DB driver for PDO::mysql, based on the mysqli driver
 *
 * @author    Team Sherbet Fountain (Rob & Josh)
 * @category  Database
 * @copyright Mayden
 */
class DB_pdoMysql extends DB_common
{
    /**
     * The DB driver type (mysql, oci8, odbc, etc.)
     * @var string
     */
    var $phptype = 'pdoMysql';

    /**
     * The database syntax variant to be used (db2, access, etc.), if any
     * @var string
     */
    var $dbsyntax = 'pdoMysql';

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
    var $features = array(
        'limit'         => 'alter',
        'new_link'      => false,
        'numrows'       => true,
        'pconnect'      => false,
        'prepare'       => false,
        'ssl'           => true,
        'transactions'  => true,
    );

    /**
     * A mapping of native error codes to DB error codes
     * @var array
     */
    var $errorcode_map = array(
        1004 => DB_ERROR_CANNOT_CREATE,
        1005 => DB_ERROR_CANNOT_CREATE,
        1006 => DB_ERROR_CANNOT_CREATE,
        1007 => DB_ERROR_ALREADY_EXISTS,
        1008 => DB_ERROR_CANNOT_DROP,
        1022 => DB_ERROR_ALREADY_EXISTS,
        1044 => DB_ERROR_ACCESS_VIOLATION,
        1046 => DB_ERROR_NODBSELECTED,
        1048 => DB_ERROR_CONSTRAINT,
        1049 => DB_ERROR_NOSUCHDB,
        1050 => DB_ERROR_ALREADY_EXISTS,
        1051 => DB_ERROR_NOSUCHTABLE,
        1054 => DB_ERROR_NOSUCHFIELD,
        1061 => DB_ERROR_ALREADY_EXISTS,
        1062 => DB_ERROR_ALREADY_EXISTS,
        1064 => DB_ERROR_SYNTAX,
        1091 => DB_ERROR_NOT_FOUND,
        1100 => DB_ERROR_NOT_LOCKED,
        1136 => DB_ERROR_VALUE_COUNT_ON_ROW,
        1142 => DB_ERROR_ACCESS_VIOLATION,
        1146 => DB_ERROR_NOSUCHTABLE,
        1216 => DB_ERROR_CONSTRAINT,
        1217 => DB_ERROR_CONSTRAINT,
        1356 => DB_ERROR_DIVZERO,
        1451 => DB_ERROR_CONSTRAINT,
        1452 => DB_ERROR_CONSTRAINT,
    );

    /**
     * The raw database connection created by PHP
     * @var resource
     */
    var $connection;

    /**
     * A copy of the last pdostatement object
     */
    var $lastStatement = null;

    /**
     * The DSN information for connecting to a database
     * @var array
     */
    var $dsn = array();


    /**
     * Should data manipulation queries be committed automatically?
     * @var bool
     * @access private
     */
    var $autocommit = true;

    /**
     * The quantity of transactions begun
     *
     * {@internal  While this is private, it can't actually be designated
     * private in PHP 5 because it is directly accessed in the test suite.}}
     *
     * @var integer
     * @access private
     */
    var $transaction_opcount = 0;

    /**
     * The database specified in the DSN
     *
     * It's a fix to allow calls to different databases in the same script.
     *
     * @var string
     * @access private
     */
    var $_db = '';

    /**
     * This constructor calls <kbd>$this->DB_common()</kbd>
     *
     * @return void
     */
    function __construct()
    {
        parent::__construct();
    }

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
     * $dsn = array(
     *     'phptype'  => 'mysqli',
     *     'username' => 'someuser',
     *     'password' => 'apasswd',
     *     'hostspec' => 'localhost',
     *     'database' => 'thedb',
     *     'key'      => 'client-key.pem',
     *     'cert'     => 'client-cert.pem',
     *     'ca'       => 'cacert.pem',
     *     'capath'   => '/path/to/ca/dir',
     *     'cipher'   => 'AES',
     * );
     *
     * $options = array(
     *     'ssl' => true,
     * );
     *
     * $db = DB::connect($dsn, $options);
     * if (PEAR::isError($db)) {
     *     die($db->getMessage());
     * }
     * </code>
     *
     * @param array $dsn         the data source name
     * @param bool  $persistent  should the connection be persistent?
     *
     * @return int  DB_OK on success. A DB_Error object on failure.
     */
    function connect($dsn, $persistent = false)
    {
        if (!PEAR::loadExtension('pdo_mysql')) {
            return $this->raiseError(DB_ERROR_EXTENSION_NOT_FOUND);
        }

        $this->dsn = $dsn;
        if ($dsn['dbsyntax']) {
            $this->dbsyntax = $dsn['dbsyntax'];
        }

        $ini = ini_get('track_errors');
        @ini_set('track_errors', 1);
        $php_errormsg = '';

        $options = array();

        if (isset($dsn['port']) && !empty($dns['port'])) {
            $hostSpec = $dsn['hostspec'] . ':' . $dsn['port'];
        } else {
            $hostSpec = $dsn['hostspec'];
        }

        $generatedDsn = sprintf('mysql:host=%s;dbname=%s', $hostSpec, $dsn['database']);

        if (((int) $this->getOption('ssl')) === 1) {
            $options = array_merge($options, array(
                PDO::MYSQL_ATTR_SSL_CERT   => empty($dsn['cert'])   ? null : $dsn['cert'],
                PDO::MYSQL_ATTR_SSL_KEY    => empty($dsn['key'])    ? null : $dsn['key'],
                PDO::MYSQL_ATTR_SSL_CA     => empty($dsn['ca'])     ? null : $dsn['ca'],
                PDO::MYSQL_ATTR_SSL_CAPATH => empty($dsn['capath']) ? null : $dsn['capath'],
                PDO::MYSQL_ATTR_SSL_CIPHER => empty($dsn['cipher']) ? null : $dsn['cipher'],
            ));
        }

        try {
            $this->connection = @new PDO($generatedDsn, $dsn['username'], $dsn['password'], $options);
        } catch (PDOException $e) {
            return $this->raiseError(DB_ERROR_CONNET_FAILED, null, null, null, $e->getMessage());
        }

        @ini_set('track_errors', $ini);

        if ($dsn['database']) {
            $this->_db = $dsn['database'];
        }

        return DB_OK;
    }

    /**
     * Disconnects from the database server
     *
     * @return bool  TRUE on success, FALSE on failure
     */
    function disconnect()
    {
        $this->connection = null;
        return true;
    }

    /**
     * Sends a query to the database server
     *
     * @param string  the SQL query string
     *
     * @return mixed  + a PHP result resrouce for successful SELECT queries
     *                + the DB_OK constant for other successful queries
     *                + a DB_Error object on failure
     */
    function simpleQuery($query)
    {
        $ismanip = $this->_checkManip($query);
        $this->last_query = $query;
        $query = $this->modifyQuery($query);
        if (!$this->_db) {
            return $this->myRaiseError(DB_ERROR_NODBSELECTED);
        }
        if (!$this->autocommit && $ismanip) {
            if ($this->transaction_opcount == 0) {
                $result = $this->connection->beginTransaction();
                if (!$result) {
                    return $this->myRaiseError();
                }
            }
            $this->transaction_opcount++;
        }
        $result = $this->connection->query($query);
        if (!$result) {
            return $this->myRaiseError();
        }
        if (is_object($result)) {
            $this->lastStatement = $result;
            return $result;
        }
        return DB_OK;
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
    function nextResult($result)
    {
        return false;
    }

    /**
     * Places a row from the result set into the given array
     *
     * Formating of the array and the data therein are configurable.
     * See DB_result::fetchInto() for more information.
     *
     * This method is not meant to be called directly.  Use
     * DB_result::fetchInto() instead.  It can't be declared "protected"
     * because DB_result is a separate object.
     *
     * @param resource $result    the query result resource
     * @param array    $arr       the referenced array to put the data in
     * @param int      $fetchmode how the resulting array should be indexed
     * @param int      $rownum    the row number to fetch (0 = first row)
     *
     * @return mixed  DB_OK on success, NULL when the end of a result set is
     *                 reached or on failure
     *
     * @see DB_result::fetchInto()
     */
    function fetchInto($result, &$arr, $fetchmode, $rownum = null)
    {
        if ($fetchmode & DB_FETCHMODE_ASSOC) {
            $arr = @$result->fetch(PDO::FETCH_ASSOC, null, $rownum);
            if ($this->options['portability'] & DB_PORTABILITY_LOWERCASE && $arr) {
                $arr = array_change_key_case($arr, CASE_LOWER);
            }
        } else {
            $arr = @$result->fetch(PDO::FETCH_NUM);
        }
        if (!$arr) {
            return null;
        }
        if ($this->options['portability'] & DB_PORTABILITY_RTRIM) {
            /*
             * Even though this DBMS already trims output, we do this because
             * a field might have intentional whitespace at the end that
             * gets removed by DB_PORTABILITY_RTRIM under another driver.
             */
            $this->_rtrimArrayValues($arr);
        }
        if ($this->options['portability'] & DB_PORTABILITY_NULL_TO_EMPTY) {
            $this->_convertNullArrayValuesToEmpty($arr);
        }
        return DB_OK;
    }

    /**
     * Deletes the result set and frees the memory occupied by the result set
     *
     * This method is not meant to be called directly.  Use
     * DB_result::free() instead.  It can't be declared "protected"
     * because DB_result is a separate object.
     *
     * @param resource $result  PHP's query result resource
     *
     * @return bool  TRUE on success, FALSE if $result is invalid
     *
     * @see DB_result::free()
     */
    function freeResult($result)
    {
        $result = null;
        return is_resource($result) ? true : false;
    }

    /**
     * Gets the number of columns in a result set
     *
     * This method is not meant to be called directly.  Use
     * DB_result::numCols() instead.  It can't be declared "protected"
     * because DB_result is a separate object.
     *
     * @param resource $result  PHP's query result resource
     *
     * @return int  the number of columns.  A DB_Error object on failure.
     *
     * @see DB_result::numCols()
     */
    function numCols($result)
    {
        $cols = @$result->columnCount();
        if (!$cols) {
            return $this->myRaiseError();
        }
        return $cols;
    }

    /**
     * Gets the number of rows in a result set
     *
     * This method is not meant to be called directly.  Use
     * DB_result::numRows() instead.  It can't be declared "protected"
     * because DB_result is a separate object.
     *
     * @param resource $result  PHP's query result resource
     *
     * @return int  the number of rows.  A DB_Error object on failure.
     *
     * @see DB_result::numRows()
     */
    function numRows($result)
    {
        $rows = @$result->rowCount();
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
     * @return int  DB_OK on success.  A DB_Error object if the driver
     *               doesn't support auto-committing transactions.
     */
    function autoCommit($onoff = false)
    {
        // XXX if $this->transaction_opcount > 0, we should probably
        // issue a warning here.
        $this->autocommit = $onoff ? true : false;
        return DB_OK;
    }

    /**
     * Commits the current transaction
     *
     * @return int  DB_OK on success.  A DB_Error object on failure.
     */
    function commit()
    {
        if ($this->transaction_opcount > 0) {
            if (!$this->_db) {
                return $this->myRaiseError(DB_ERROR_NODBSELECTED);
            }
            $result = $this->connection->commit();
            $this->transaction_opcount = 0;
            if (!$result) {
                return $this->myRaiseError();
            }
        }
        return DB_OK;
    }

    /**
     * Reverts the current transaction
     *
     * @return int  DB_OK on success.  A DB_Error object on failure.
     */
    function rollback()
    {
        if ($this->transaction_opcount > 0) {
            if (!$this->_db) {
                return $this->myRaiseError(DB_ERROR_NODBSELECTED);
            }
            $result = $this->connection->rollback();
            $this->transaction_opcount = 0;
            if (!$result) {
                return $this->myRaiseError();
            }
        }
        return DB_OK;
    }

    /**
     * Determines the number of rows affected by a data maniuplation query
     *
     * 0 is returned for queries that don't manipulate data.
     *
     * @return int  the number of rows.  A DB_Error object on failure.
     */
    function affectedRows()
    {
        if (($this->lastStatement === null) || !is_object($this->lastStatement)) {
            $this->myRaiseError();
        }

        if ($this->_last_query_manip) {
            return $this->lastStatement->rowCount();
        } else {
            return 0;
        }
    }

    /**
     * Returns the next free id in a sequence
     *
     * @param string  $seq_name  name of the sequence
     * @param boolean $ondemand  when true, the seqence is automatically
     *                            created if it does not exist
     *
     * @return int  the next id number in the sequence.
     *               A DB_Error object on failure.
     *
     * @see DB_common::nextID(), DB_common::getSequenceName(),
     *      DB_mysqli::createSequence(), DB_mysqli::dropSequence()
     */
    function nextId($seq_name, $ondemand = true)
    {
        $seqname = $this->getSequenceName($seq_name);
        do {
            $repeat = 0;
            $this->pushErrorHandling(PEAR_ERROR_RETURN);
            $result = $this->query('UPDATE ' . $seqname
                                   . ' SET id = LAST_INSERT_ID(id + 1)');
            $this->popErrorHandling();
            if ($result === DB_OK) {
                // COMMON CASE
                $id = @$this->connection->lastInsertId();
                if ($id != 0) {
                    return $id;
                }

                // EMPTY SEQ TABLE
                // Sequence table must be empty for some reason,
                // so fill it and return 1
                // Obtain a user-level lock
                $result = $this->getOne('SELECT GET_LOCK('
                                        . "'${seqname}_lock', 10)");
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }
                if ($result == 0) {
                    return $this->myRaiseError(DB_ERROR_NOT_LOCKED);
                }

                // add the default value
                $result = $this->query('REPLACE INTO ' . $seqname
                                       . ' (id) VALUES (0)');
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }

                // Release the lock
                $result = $this->getOne('SELECT RELEASE_LOCK('
                                        . "'${seqname}_lock')");
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }
                // We know what the result will be, so no need to try again
                return 1;

            } elseif ($ondemand && DB::isError($result) &&
                $result->getCode() == DB_ERROR_NOSUCHTABLE) {
                // ONDEMAND TABLE CREATION
                $result = $this->createSequence($seq_name);

                // Since createSequence initializes the ID to be 1,
                // we do not need to retrieve the ID again (or we will get 2)
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                } else {
                    // First ID of a newly created sequence is 1
                    return 1;
                }

            } elseif (DB::isError($result) &&
                      $result->getCode() == DB_ERROR_ALREADY_EXISTS) {
                // BACKWARDS COMPAT
                // see _BCsequence() comment
                $result = $this->_BCsequence($seqname);
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
     * @param string $seq_name  name of the new sequence
     *
     * @return int  DB_OK on success.  A DB_Error object on failure.
     *
     * @see DB_common::createSequence(), DB_common::getSequenceName(),
     *      DB_mysqli::nextID(), DB_mysqli::dropSequence()
     */
    function createSequence($seq_name)
    {
        $seqname = $this->getSequenceName($seq_name);
        $res = $this->query('CREATE TABLE ' . $seqname
                            . ' (id INTEGER UNSIGNED AUTO_INCREMENT NOT NULL,'
                            . ' PRIMARY KEY(id))');
        if (DB::isError($res)) {
            return $res;
        }
        // insert yields value 1, nextId call will generate ID 2
        return $this->query("INSERT INTO ${seqname} (id) VALUES (0)");
    }

    /**
     * Deletes a sequence
     *
     * @param string $seq_name  name of the sequence to be deleted
     *
     * @return int  DB_OK on success.  A DB_Error object on failure.
     *
     * @see DB_common::dropSequence(), DB_common::getSequenceName(),
     *      DB_mysql::nextID(), DB_mysql::createSequence()
     */
    function dropSequence($seq_name)
    {
        return $this->query('DROP TABLE ' . $this->getSequenceName($seq_name));
    }

    /**
     * Backwards compatibility with old sequence emulation implementation
     * (clean up the dupes)
     *
     * @param string $seqname  the sequence name to clean up
     *
     * @return bool  true on success.  A DB_Error object on failure.
     *
     * @access private
     */
    function _BCsequence($seqname)
    {
        // Obtain a user-level lock... this will release any previous
        // application locks, but unlike LOCK TABLES, it does not abort
        // the current transaction and is much less frequently used.
        $result = $this->getOne("SELECT GET_LOCK('${seqname}_lock',10)");
        if (DB::isError($result)) {
            return $result;
        }
        if ($result == 0) {
            // Failed to get the lock, can't do the conversion, bail
            // with a DB_ERROR_NOT_LOCKED error
            return $this->myRaiseError(DB_ERROR_NOT_LOCKED);
        }

        $highest_id = $this->getOne("SELECT MAX(id) FROM ${seqname}");
        if (DB::isError($highest_id)) {
            return $highest_id;
        }

        // This should kill all rows except the highest
        // We should probably do something if $highest_id isn't
        // numeric, but I'm at a loss as how to handle that...
        $result = $this->query('DELETE FROM ' . $seqname
                               . " WHERE id <> $highest_id");
        if (DB::isError($result)) {
            return $result;
        }

        // If another thread has been waiting for this lock,
        // it will go thru the above procedure, but will have no
        // real effect
        $result = $this->getOne("SELECT RELEASE_LOCK('${seqname}_lock')");
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
     * @return string  quoted identifier string
     *
     * @see DB_common::quoteIdentifier()
     * @since Method available since Release 1.6.0
     */
    function quoteIdentifier($str)
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    /**
     * Escapes a string according to the current DBMS's standards
     *
     * @param string $str  the string to be escaped
     *
     * @return string  the escaped string
     *
     * @see DB_common::quoteSmart()
     * @since Method available since Release 1.6.0
     */
    function escapeSimple($str)
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
     *                         execution of the statement.  Quantity of items
     *                         passed must match quantity of placeholders in
     *                         query:  meaning 1 placeholder for non-array
     *                         parameters or 1 placeholder per array element.
     *
     * @return string  the query string with LIMIT clauses added
     *
     * @access protected
     */
    function modifyLimitQuery($query, $from, $count, $params = array())
    {
        if (DB::isManip($query) || $this->_next_query_manip) {
            return $query . " LIMIT $count";
        } else {
            return $query . " LIMIT $from, $count";
        }
    }

    /**
     * Produces a DB_Error object regarding the current problem
     *
     * @param int $errno  if the error is being manually raised pass a
     *                     DB_ERROR* constant here.  If this isn't passed
     *                     the error information gathered from the DBMS.
     *
     * @return object  the DB_Error object
     *
     * @see DB_common::raiseError(),
     *      DB_mysqli::errorNative(), DB_common::errorCode()
     */
    function myRaiseError($errno = null)
    {
        // if ($errno === null) {
        //     if ($this->options['portability'] & DB_PORTABILITY_ERRORS) {
        //         $this->errorcode_map[1022] = DB_ERROR_CONSTRAINT;
        //         $this->errorcode_map[1048] = DB_ERROR_CONSTRAINT_NOT_NULL;
        //         $this->errorcode_map[1062] = DB_ERROR_CONSTRAINT;
        //     } else {
        //         // Doing this in case mode changes during runtime.
        //         $this->errorcode_map[1022] = DB_ERROR_ALREADY_EXISTS;
        //         $this->errorcode_map[1048] = DB_ERROR_CONSTRAINT;
        //         $this->errorcode_map[1062] = DB_ERROR_ALREADY_EXISTS;
        //     }
        //     $errno = $this->errorCode($this->connection->errorCode());
        // }
        $error = $this->connection->errorInfo();
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
    function errorNative()
    {
        return $this->connection->errorCode();
    }

    /**
     * Returns information about a table or a result set
     *
     * @param object|string  $result  DB_result object from a query or a
     *                                 string containing the name of a table.
     *                                 While this also accepts a query result
     *                                 resource identifier, this behavior is
     *                                 deprecated.
     * @param int            $mode    a valid tableInfo mode
     *
     * @return array  an associative array with the information requested.
     *                 A DB_Error object on failure.
     *
     * @see DB_common::setOption()
     */
    function tableInfo($result, $mode = null)
    {
        if (is_string($result)) {
            // Fix for bug #11580.
            if (!$this->_db) {
                return $this->myRaiseError(DB_ERROR_NODBSELECTED);
            }

            /*
             * Probably received a table name.
             * Create a result resource identifier.
             */
            $id = @$this->simpleQuery("SELECT * FROM $result LIMIT 0");
            $got_string = true;
        } elseif (is_object($result)) {
            /*
             * Probably received a result object.
             * Extract the result resource identifier.
             */
            $id = $result;
            $got_string = false;
        } else {
            $this->myRaiseError();
        }

        if (!is_object($id) || !is_a($id, 'PDOStatement')) {
            return $this->myRaiseError(DB_ERROR_NEED_MORE_DATA);
        }

        if ($this->options['portability'] & DB_PORTABILITY_LOWERCASE) {
            $case_func = 'strtolower';
        } else {
            $case_func = 'strval';
        }

        $count = $id->columnCount();
        $res = array();

        if ($mode) {
            $res['num_fields'] = $count;
        }

        for ($i = 0; $i < $count; $i++) {
            $tmp = @$id->getColumnMeta($i);

            $res[$i] = array(
                'table' => $tmp['table'],
                'name'  => $tmp['name'],
                'type'  => isset($tmp['native_type']) ? $tmp['native_type'] : 'unknown',
                'len'   => $tmp['len'],
                'flags' => $tmp['flags'],
            );

            if ($mode & DB_TABLEINFO_ORDER) {
                $res['order'][$res[$i]['name']] = $i;
            }
            if ($mode & DB_TABLEINFO_ORDERTABLE) {
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
     * @return string  the SQL query string or null if the driver doesn't
     *                  support the object type requested
     *
     * @access protected
     * @see DB_common::getListOf()
     */
    function getSpecialQuery($type)
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
}
