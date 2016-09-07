<?php
namespace Mayden\Pineapple\Test\DB\Driver;

use Mayden\Pineapple\Util;
use Mayden\Pineapple\DB;
use Mayden\Pineapple\DB\Driver\Common;

/**
 * A null 'test' driver for Pineapple. This serves two purposes: one to act as a scaffold to test scope refactoring,
 * and to provide a test facility for higher levels of abstraction.
 */
class TestDriver extends Common
{
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

    public function __construct()
    {
        parent::__construct();
    }

    public function connect($dsn, $persistent = false)
    {
        return DB::DB_OK;
    }

    public function disconnect()
    {
        return true;
    }

    public function simpleQuery($query)
    {
        if (preg_match('/^SELECT', $query)) {
            $this->lastQueryType = 'SELECT';
            return [
                'type' => 'resultResource',
                'results' => [
                    [
                        'id' => 1,
                        'data' => 'test1',
                    ],
                    [
                        'id' => 2,
                        'data' => 'test2',
                    ]
                ]
            ];
        } elseif (preg_match('^INSERT', $query)) {
            // this may not be correct
            $this->lastQueryType = 'INSERT';
            return DB::OK;
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
        if (!is_null($rownum)) {
            // hand-picked row
            if (!isset($result['results'][$rownum])) {
                // if the row isn't present, don't bother continuing
                return null;
            }
            $row = $result['results'][$rownum];
        } else {
            $row = current($result['results']);
            next($result['results']); // advance ptr, even if we're not using it

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

    public function freeResult($result)
    {
        if (!isset($result['type']) || ($result['type'] != 'resultResource')) {
            return false;
        }

        if (isset($result['feignFailure']) && ($result['feignFailure'] === true)) {
            return false;
        }

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

    public function quoteIdentifier($str)
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    public function escapeSimple($str)
    {
        // THE WORST
        return addslashes($str);
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
}
