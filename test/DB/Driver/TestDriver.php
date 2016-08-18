<?php
namespace Mayden\Pineapple\Test\DB\Driver;

use Mayden\Pineapple\Util;
use Mayden\Pineapple\DB;
use Mayden\Pineapple\DB\Driver\Common;

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
            return [
                [
                    'id' => 1,
                    'data' => 'test1',
                ],
                [
                    'id' => 2,
                    'data' => 'test2',
                ]
            ];
        } elseif (preg_match('^INSERT', $query)) {
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
    }
}
