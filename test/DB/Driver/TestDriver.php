<?php
namespace Pineapple\Test\DB\Driver;

use Pineapple\Util;
use Pineapple\DB;
use Pineapple\DB\Driver\Common;

/**
 * A null 'test' driver for Pineapple. This serves two purposes: one to act as a scaffold to test scope refactoring,
 * and to provide a test facility for higher levels of abstraction.
 */
class TestDriver extends Common
{
    private $lastResult = null;
    private $hasFreed = false;

    protected $features = [
        'limit' => 'alter',
        'numrows' => true,
        'prepare' => false,
        'transactions' => true,
    ];

    protected $errorcode_map = [
        1000 => DB::DB_OK,
        1001 => DB::DB_ERROR,
        1002 => DB::DB_ERROR_ACCESS_VIOLATION,
        1003 => DB::DB_ERROR_ALREADY_EXISTS,
        1004 => DB::DB_ERROR_CANNOT_CREATE,
        1005 => DB::DB_ERROR_CANNOT_DROP,
        1006 => DB::DB_ERROR_CONNECT_FAILED,
        1007 => DB::DB_ERROR_CONSTRAINT,
        1008 => DB::DB_ERROR_CONSTRAINT_NOT_NULL,
        1009 => DB::DB_ERROR_DIVZERO,
        1010 => DB::DB_ERROR_EXTENSION_NOT_FOUND,
        1011 => DB::DB_ERROR_INVALID,
        1012 => DB::DB_ERROR_INVALID_DATE,
        1013 => DB::DB_ERROR_INVALID_DSN,
        1014 => DB::DB_ERROR_INVALID_NUMBER,
        1015 => DB::DB_ERROR_MISMATCH,
        1016 => DB::DB_ERROR_NEED_MORE_DATA,
        1017 => DB::DB_ERROR_NODBSELECTED,
        1018 => DB::DB_ERROR_NOSUCHDB,
        1019 => DB::DB_ERROR_NOSUCHFIELD,
        1020 => DB::DB_ERROR_NOSUCHTABLE,
        1021 => DB::DB_ERROR_NOT_CAPABLE,
        1022 => DB::DB_ERROR_NOT_FOUND,
        1023 => DB::DB_ERROR_NOT_LOCKED,
        1024 => DB::DB_ERROR_SYNTAX,
        1025 => DB::DB_ERROR_UNSUPPORTED,
        1026 => DB::DB_ERROR_TRUNCATED,
        1027 => DB::DB_ERROR_VALUE_COUNT_ON_ROW,
    ];

    private $lastQueryType = null;
    private $sequenceCounter = 1000;
    protected $connection = false;
    protected $autocommit = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function setPrepareFeature($flag)
    {
        $this->features['prepare'] = $flag ? true : false;
    }

    public function stubConnect()
    {
        $this->connection = true;
    }

    public function disconnect()
    {
        $this->connection = false;
        return true;
    }

    public function prepare($query)
    {
        // this is a very specific name relied upon by neighbouring classes.
        $this->lastQuery = $query;

        // Common::prepare doesn't inspect for errors, but query checks for errors
        // post prepare, so watch for syntax markers
        if (preg_match('/PREPFAIL/', $query)) {
            return $this->raiseError(DB::DB_ERROR_SYNTAX);
        }

        return parent::prepare($query);
    }

    public function simpleQuery($query)
    {
        $this->lastResult = null;

        // this is a very specific name relied upon by neighbouring classes.
        $this->lastQuery = $query;
        $this->lastParameters = [];

        if (preg_match('/^(SELECT|BREAKINGSEL)/', $query)) {
            // SELECT: a regular SELECT that returns data successfully
            // BREAKINGSEL: a SELECT that returns data but breaks on first fetch
            $this->lastQueryType = 'SELECT';
            $results = [
                'type' => 'resultResource',
                'breaksEasily' => preg_match('/^BREAKINGSEL/', $query) ? true : false,
                'results' => [],
            ];

            // generate 20 test results
            for ($i = 1; $i <= 20; $i++) {
                $results['results'][] = [
                    'id' => $i,
                    'data' => 'test' . $i,
                ];
            }
            return $results;
        } elseif (preg_match('/^(BREAKING)?SINGLECOLSEL/', $query)) {
            $this->lastQueryType = 'SELECT';
            $results = [
                'type' => 'resultResource',
                'breaksEasily' => preg_match('/^BREAKINGSINGLECOLSEL/', $query) ? true : false,
                'results' => [],
            ];

            // generate 20 test results
            for ($i = 1; $i <= 20; $i++) {
                $results['results'][] = [
                    'id' => $i,
                ];
            }
            return $results;
        } elseif (preg_match('/^EMPTYSEL/', $query)) {
            return [
                'type' => 'resultResource',
                'breaksEasily' => false,
                'results' => [],
            ];
        } elseif (preg_match('/^INSERT/', $query)) {
            // this may not be correct
            $this->lastQueryType = 'INSERT';
            return DB::DB_OK;
        } else {
            return $this->raiseError(DB::DB_ERROR_NOSUCHTABLE);
        }
    }

    public function nextResult($result)
    {
        return false;
    }

    public function fetchInto($result, &$arr, $fetchmode, $rownum = null)
    {
        if ($this->lastResult !== $result) {
            $this->lastResult = $result;
        }

        if (is_array($result) && isset($result['breaksEasily']) && $result['breaksEasily']) {
            return $this->raiseError(DB::DB_ERROR_TRUNCATED);
        }

        if (!is_null($rownum)) {
            // hand-picked row
            if (!isset($this->lastResult['results'][$rownum])) {
                // if the row isn't present, don't bother continuing
                return null;
            }
            $row = $this->lastResult['results'][$rownum];
        } else {
            $row = current($this->lastResult['results']);
            next($this->lastResult['results']); // advance ptr, even if we're not using it

            if ($row === false) {
                return null;
            }
        }

        if ($fetchmode & DB::DB_FETCHMODE_ASSOC) {
            // we're already associative
            $arr = $row;
        } else {
            $arr = array_values($row);
        }

        return DB::DB_OK;
    }

    public function resetFreeFlag()
    {
        $this->hasFreed = false;
    }

    public function getFreeFlag()
    {
        return $this->hasFreed;
    }

    public function freeResult($result)
    {
        if (!isset($result['type']) || ($result['type'] !== 'resultResource')) {
            return false;
        }

        if (isset($result['feignFailure']) && ($result['feignFailure'] === true)) {
            return $this->myRaiseError();
        }

        $this->hasFreed = true;
        return true;
    }

    public function numCols($result)
    {
        if (!isset($result['type']) || ($result['type'] != 'resultResource')) {
            return $this->myRaiseError();
        }

        if (isset($result['results'][0])) {
            return count($result['results'][0]);
        }

        // honestly the behaviour is undefined for empty result handles. great!
        return 0;
    }

    public function numRows($result)
    {
        if (!isset($result['type']) || ($result['type'] != 'resultResource')) {
            return $this->myRaiseError();
        }

        return count($result['results']);
    }

    public function stubNumRows($result)
    {
        // call the _parent_ numRows because we overrode it above
        return parent::numRows($result);
    }

    private function myRaiseError($errno = null)
    {
        return $this->raiseError(
            12345,
            null,
            null,
            null,
            'b0rked'
        );
    }

    public function tableInfo($result, $mode = null)
    {
        if ($result === null) {
            return $this->myRaiseError();
        }

        return [
            [
                'table' => 'bonzotable',
                'name' => 'bonzoname',
                'type' => 'integer',
                'len' => '12',
                'flags' => 'NOT NULL',
            ],
            [
                'table' => 'bonzotable',
                'name' => 'banjoname',
                'type' => 'string',
                'len' => '20',
                'flags' => '',
            ]
        ];
    }

    public function stubTableInfo($result, $mode = null)
    {
        return parent::tableInfo($result, $mode);
    }

    public function stubGetSpecialQuery($type)
    {
        return parent::getSpecialQuery($type);
    }

    public function stubCheckManip($query)
    {
        return parent::checkManip($query);
    }

    public function stubRtrimArrayValues(&$array)
    {
        return $this->_rtrimArrayValues($array);
    }

    public function stubConvertNullArrayValuesToEmpty(&$array)
    {
        return $this->_convertNullArrayValuesToEmpty($array);
    }

    public function buildDetokenisedQuery($stmt, $data = [])
    {
        return $this->executeEmulateQuery($stmt, $data);
    }

    protected function executeEmulateQuery($stmt, $data = [])
    {
        if (preg_match('/FAILURE/', $this->preparedQueries[$stmt])) {
            return $this->raiseError(DB::DB_ERROR_SYNTAX);
        } else {
            return parent::executeEmulateQuery($stmt, $data);
        }
    }

    public function stubModifyQuery($query)
    {
        return parent::modifyQuery($query);
    }

    public function stubModifyLimitQuery($query, $from, $count, $params = [])
    {
        return parent::modifyLimitQuery($query, $from, $count, $params);
    }

    public function modifyLimitQuery($query, $from, $count, $params = [])
    {
        if (preg_match('/FAILURE/', $query)) {
            return $this->raiseError(DB::DB_ERROR_SYNTAX);
        }
        return parent::modifyLimitQuery($query, $from, $count, $params);
    }

    protected function getSpecialQuery($type)
    {
        switch ($type) {
            case 'returnnull':
                return null;
                break;

            case 'thing':
                return ['thing', 'stuff'];
                break;

            case 'query':
                return 'SELECT foo FROM bar';
                break;

            default:
                return $this->raiseError(DB::DB_ERROR_DIVZERO);
        }
    }
}
