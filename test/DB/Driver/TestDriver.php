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

    var $phptype = 'test';
    var $dbsyntax = 'test';
    var $features = [
        'limit' => 'alter',
        'new_link' => false,
        'numrows' => true,
        'pconnect' => false,
        'prepare' => false,
        'ssl' => true,
        'transactions' => true,
    ];

    var $errorcode_map = [
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
    protected $dsn = null;
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

    public function connect($dsn, $persistent = false)
    {
        $this->dsn = $dsn;
        $debug = $this->getOption('debug');
        if (!Util::isError($debug) && ($debug === 'please fail')) {
            return $this->myRaiseError();
        }
        $this->connection = true;
        return DB::DB_OK;
    }

    public function disconnect()
    {
        $this->connection = false;
        return true;
    }

    public function prepare($query)
    {
        // this is a very specific name relied upon by neighbouring classes.
        $this->last_query = $query;
        return parent::prepare($query);
    }

    public function simpleQuery($query)
    {
        $this->lastResult = null;

        // this is a very specific name relied upon by neighbouring classes.
        $this->last_query = $query;
        $this->last_parameters = [];

        if (preg_match('/^SELECT/', $query)) {
            $this->lastQueryType = 'SELECT';
            $results = [
                'type' => 'resultResource',
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
        } elseif (preg_match('/^INSERT/', $query)) {
            // this may not be correct
            $this->lastQueryType = 'INSERT';
            return DB::DB_OK;
        } else {
            return $this->myRaiseError();
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

    public function autoCommit($onoff = false)
    {
        $this->autocommit = $onoff ? true : false;
        return DB::DB_OK;
    }

    public function commit()
    {
        return DB::DB_OK;
    }

    public function rollback()
    {
        return DB::DB_OK;
    }

    public function affectedRows()
    {
        if ($this->lastQueryType === null) {
            return $this->myRaiseError();
        } elseif ($this->lastQueryType === 'INSERT') {
            return 0;
        }

        return 2;
    }

    public function nextId($seq_name, $ondemand = true)
    {
        if ($seq_name === 'badsequence') {
            return $this->myRaiseError();
        }
        return $this->sequenceCounter++;
    }

    public function createSequence($seq_name)
    {
        if ($seq_name === 'badsequence') {
            return $this->myRaiseError();
        }
        return DB::DB_OK;
    }

    public function dropSequence($seq_name)
    {
        if ($seq_name === 'badsequence') {
            return $this->myRaiseError();
        }
        return DB::DB_OK;
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

    public function errorNative()
    {
        return 54321;
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

    public function buildDetokenisedQuery($stmt, $data = [])
    {
        return $this->executeEmulateQuery($stmt, $data);
    }
}
