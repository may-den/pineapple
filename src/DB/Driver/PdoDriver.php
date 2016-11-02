<?php
namespace Pineapple\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Error;
use Pineapple\DB\StatementContainer;
use Pineapple\DB\Exception\DriverException;
use Pineapple\DB\Driver\Components\AnsiSqlErrorCodes;
use Pineapple\DB\Driver\Components\PdoCommonMethods;

use PDO;
use PDOStatement;
use PDOException;

/**
 * A PEAR DB driver that uses PDO as an underlying database layer.
 *
 * @author     Rob Andrews <rob@aphlor.org>
 * @copyright  BSD-2-Clause
 * @package    Database
 * @version    Introduced in Pineapple 0.3.0
 */
class PdoDriver extends Common implements DriverInterface
{
    use AnsiSqlErrorCodes;
    use PdoCommonMethods;

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

    // @var PDO Our PDO connection
    protected $connection = null;

    /**
     * A copy of the last pdostatement object
     * @var StatementContainer
     */
    private $lastStatement = null;

    /**
     * Should data manipulation queries be committed automatically?
     * @var bool
     */
    protected $autocommit = true;

    /**
     * The quantity of transactions begun
     *
     * @var integer
     */
    private $transactionOpcount = 0;

    /**
     * Set the PDO connection handle in the object
     *
     * @param PDO        $connection A constructed PDO connection handle
     * @return PdoDriver             The constructed Pineapple\DB\Driver\PdoDriver object
     */
    public function setConnectionHandle(PDO $connection)
    {
        $this->connection = $connection;

        return $this;
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

        // query driver options, none by default
        $queryDriverOptions = [];

        if (!$this->connected()) {
            return $this->myRaiseError(DB::DB_ERROR_NODBSELECTED);
        }

        if (!$this->autocommit && $ismanip) {
            if ($this->transactionOpcount === 0) {
                try {
                    $return = $this->connection->beginTransaction();
                    // sqlite supports transactions so this can't be tested right now
                    // @codeCoverageIgnoreStart
                } catch (PDOException $transactionException) {
                    return $this->raiseError(DB::DB_ERROR, null, null, $transactionException->getMessage());
                    // @codeCoverageIgnoreEnd
                }

                if ($return === false) {
                    // sqlite supports transactions so this can't be tested right now
                    // @codeCoverageIgnoreStart
                    return $this->raiseError(
                        DB::DB_ERROR,
                        null,
                        null,
                        self::formatErrorInfo($this->connection->errorInfo())
                    );
                    // @codeCoverageIgnoreEnd
                }
            }
            $this->transactionOpcount++;
        }

        // enable/disable result_buffering in mysql
        // @codeCoverageIgnoreStart
        // @todo test this *thoroughly*
        if (($this->getPlatform() === 'mysql') && !$this->options['result_buffering']) {
            $queryDriverOptions[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
        }
        // @codeCoverageIgnoreEnd

        // prepare the query for execution (we can only inject the unbuffered query parameter on prepared statements)
        try {
            $statement = $this->connection->prepare($query);
        } catch (PDOException $prepareException) {
            return $this->raiseError(DB::DB_ERROR, null, null, $prepareException->getMessage());
        }

        if ($statement === false) {
            return $this->raiseError(
                DB::DB_ERROR,
                null,
                null,
                self::formatErrorInfo($this->connection->errorInfo())
            );
        }

        // execute the query
        try {
            $executeResult = $statement->execute();
        } catch (PDOException $executeException) {
            return $this->raiseError(DB::DB_ERROR, null, null, $executeException->getMessage());
        }

        if ($executeResult === false) {
            return $this->raiseError(
                DB::DB_ERROR,
                null,
                null,
                self::formatErrorInfo($statement->errorInfo())
            );
        }

        // keep this so we can perform rowCount and suchlike later
        $this->lastStatement = new StatementContainer($statement);

        // fetch queries should return the result object now
        if (!$ismanip && isset($statement) && self::getStatement($this->lastStatement)) {
            return $this->lastStatement;
        }

        // ...whilst insert/update/delete just gets a "sure, it went well" result
        return DB::DB_OK;
    }

    /**
     * Format a PDO errorInfo block as a legible string
     *
     * @param array $errorInfo The output from PDO/PDOStatement::errorInfo
     * @return string
     */
    private static function formatErrorInfo(array $errorInfo)
    {
        return sprintf(
            'SQLSTATE[%d]: (Driver code %d) %s',
            $errorInfo[0],
            $errorInfo[1],
            $errorInfo[2]
        );
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
     * @param PDOStatement $result    the query result resource
     * @param array        $arr       the referenced array to put the data in
     * @param int          $fetchmode how the resulting array should be indexed
     * @param int          $rownum    the row number to fetch (0 = first row)
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
                // this exception handle was added as the php docs implied a potential exception, which i have thus
                // far been unable to reproduce.
                // @codeCoverageIgnoreStart
            } catch (PDOException $fetchException) {
                return $this->raiseError(DB::DB_ERROR, null, null, $fetchException->getMessage());
                // @codeCoverageIgnoreEnd
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
                $commitResult = $this->connection->commit();
                // @todo cannot easily generate a failed transaction commit, don't cover this
                // @codeCoverageIgnoreStart
            } catch (PDOException $commitException) {
                return $this->raiseError(DB::DB_ERROR, null, null, $commitException->getMessage());
                // @codeCoverageIgnoreEnd
            }

            if ($commitResult === false) {
                // @todo cannot easily generate a failed transaction commit, don't cover this
                // @codeCoverageIgnoreStart
                return $this->raiseError(
                    DB::DB_ERROR,
                    null,
                    null,
                    self::formatErrorInfo($this->connection->errorInfo())
                );
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
                $rollbackResult = $this->connection->rollBack();
                // @todo cannot easily generate a failed transaction rollback, don't cover this
                // @codeCoverageIgnoreStart
            } catch (PDOException $rollbackException) {
                return $this->raiseError(DB::DB_ERROR, null, null, $rollbackException->getMessage());
                // @codeCoverageIgnoreEnd
            }

            if ($rollbackResult === false) {
                // @todo cannot easily generate a failed transaction rollback, don't cover this
                // @codeCoverageIgnoreStart
                return $this->raiseError(
                    DB::DB_ERROR,
                    null,
                    null,
                    self::formatErrorInfo($this->connection->errorInfo())
                );
                // @codeCoverageIgnoreEnd
            }

            $this->transactionOpcount = 0;
        }
        return DB::DB_OK;
    }

    /**
     * Obtain a "rationalised" database major name
     * n.b. we won't test this, in future it would make sense to see the purpose removed.
     *
     * @return string Lower-cased type of database, e.g. "mysql", "pgsql"
     * @codeCoverageIgnore
     */
    protected function getPlatform()
    {
        if (!$this->connected()) {
            return $this->raiseError(DB::DB_ERROR_NODBSELECTED);
        }

        // we're not going to support everything
        switch ($name = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
            case 'pgsql':
            case 'sqlite':
                // verbatim name
                return $name;
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
    protected static function getStatement(StatementContainer $result)
    {
        if ($result->getStatementType() === ['type' => 'object', 'class' => PDOStatement::class]) {
            return $result->getStatement();
        }
        throw new DriverException(
            'Expected ' . StatementContainer::class . ' to contain \'' . PDOStatement::class .
                '\', got ' . json_encode($result->getStatementType())
        );
    }
}
