<?php
namespace Pineapple\DB\Driver;

use Pineapple\DB;
use Pineapple\DB\Error;
use Pineapple\DB\Driver\DriverInterface;
use Pineapple\DB\StatementContainer;
use Pineapple\DB\Exception\DriverException;
use Pineapple\DB\Driver\Components\AnsiSqlErrorCodes;
use Pineapple\DB\Driver\Components\PdoCommonMethods;

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
     * @todo fix up with StatementContainer & move to PdoCommonMethods
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
    protected static function getStatement(StatementContainer $result)
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
