<?php
namespace Mayden\Pineapple\DB;

use Mayden\Pineapple\DB;

/**
 * This class implements a wrapper for a DB result set
 *
 * A new instance of this class will be returned by the DB implementation
 * after processing a query that returns data.
 *
 * @category   Database
 * @package    DB
 * @author     Stig Bakken <ssb@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: 1.8.2
 * @link       http://pear.php.net/package/DB
 */
class Result
{
    /**
     * Should results be freed automatically when there are no more rows?
     * @var boolean
     * @see DB\Common::$options
     */
    var $autofree;

    /**
     * A reference to the DB_<driver> object
     * @var object
     */
    var $dbh;

    /**
     * The current default fetch mode
     * @var integer
     * @see DB\Common::$fetchmode
     */
    var $fetchmode;

    /**
     * The name of the class into which results should be fetched when
     * DB_FETCHMODE_OBJECT is in effect
     *
     * @var string
     * @see DB\Common::$fetchmode_object_class
     */
    var $fetchmode_object_class;

    /**
     * The number of rows to fetch from a limit query
     * @var integer
     */
    var $limit_count = null;

    /**
     * The row to start fetching from in limit queries
     * @var integer
     */
    var $limit_from = null;

    /**
     * The execute parameters that created this result
     * @var array
     * @since Property available since Release 1.7.0
     */
    var $parameters;

    /**
     * The query string that created this result
     *
     * Copied here incase it changes in $dbh, which is referenced
     *
     * @var string
     * @since Property available since Release 1.7.0
     */
    var $query;

    /**
     * The query result resource id created by PHP
     * @var resource
     */
    var $result;

    /**
     * The present row being dealt with
     * @var integer
     */
    var $row_counter = null;

    /**
     * The prepared statement resource id created by PHP in $dbh
     *
     * This resource is only available when the result set was created using
     * a driver's native execute() method, not PEAR DB's emulated one.
     *
     * Copied here incase it changes in $dbh, which is referenced
     *
     * {@internal  Mainly here because the InterBase/Firebird API is only
     * able to retrieve data from result sets if the statemnt handle is
     * still in scope.}}
     *
     * @var resource
     * @since Property available since Release 1.7.0
     */
    var $statement;

    /**
     * This constructor sets the object's properties
     *
     * @param object   &$dbh     the DB object reference
     * @param resource $result   the result resource id
     * @param array    $options  an associative array with result options
     *
     * @return void
     */
    function __construct(&$dbh, $result, $options = array())
    {
        $this->autofree    = $dbh->options['autofree'];
        $this->dbh         = &$dbh;
        $this->fetchmode   = $dbh->fetchmode;
        $this->fetchmode_object_class = $dbh->fetchmode_object_class;
        $this->parameters  = $dbh->last_parameters;
        $this->query       = $dbh->last_query;
        $this->result      = $result;
        $this->statement   = empty($dbh->last_stmt) ? null : $dbh->last_stmt;
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }

    /**
     * Set options for the DB_result object
     *
     * @param string $key    the option to set
     * @param mixed  $value  the value to set the option to
     *
     * @return void
     */
    function setOption($key, $value = null)
    {
        switch ($key) {
            case 'limit_from':
                $this->limit_from = $value;
                break;
            case 'limit_count':
                $this->limit_count = $value;
        }
    }

    /**
     * Fetch a row of data and return it by reference into an array
     *
     * The type of array returned can be controlled either by setting this
     * method's <var>$fetchmode</var> parameter or by changing the default
     * fetch mode setFetchMode() before calling this method.
     *
     * There are two options for standardizing the information returned
     * from databases, ensuring their values are consistent when changing
     * DBMS's.  These portability options can be turned on when creating a
     * new DB object or by using setOption().
     *
     *   + <var>DB_PORTABILITY_LOWERCASE</var>
     *     convert names of fields to lower case
     *
     *   + <var>DB_PORTABILITY_RTRIM</var>
     *     right trim the data
     *
     * @param int $fetchmode  the constant indicating how to format the data
     * @param int $rownum     the row number to fetch (index starts at 0)
     *
     * @return mixed  an array or object containing the row's data,
     *                 NULL when the end of the result set is reached
     *                 or a DB_Error object on failure.
     *
     * @see DB\Common::setOption(), DB\Common::setFetchMode()
     */
    function &fetchRow($fetchmode = DB_FETCHMODE_DEFAULT, $rownum = null)
    {
        if ($fetchmode === DB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode === DB_FETCHMODE_OBJECT) {
            $fetchmode = DB_FETCHMODE_ASSOC;
            $object_class = $this->fetchmode_object_class;
        }
        if (is_null($rownum) && $this->limit_from !== null) {
            if ($this->row_counter === null) {
                $this->row_counter = $this->limit_from;
                // Skip rows
                if ($this->dbh->features['limit'] === false) {
                    $i = 0;
                    while ($i++ < $this->limit_from) {
                        $this->dbh->fetchInto($this->result, $arr, $fetchmode);
                    }
                }
            }
            if ($this->row_counter >= ($this->limit_from + $this->limit_count))
            {
                if ($this->autofree) {
                    $this->free();
                }
                $tmp = null;
                return $tmp;
            }
            if ($this->dbh->features['limit'] === 'emulate') {
                $rownum = $this->row_counter;
            }
            $this->row_counter++;
        }
        $res = $this->dbh->fetchInto($this->result, $arr, $fetchmode, $rownum);
        if ($res === DB_OK) {
            if (isset($object_class)) {
                // The default mode is specified in the
                // DB\Common::fetchmode_object_class property
                if ($object_class == 'stdClass') {
                    $arr = (object) $arr;
                } else {
                    $arr = new $object_class($arr);
                }
            }
            return $arr;
        }
        if ($res == null && $this->autofree) {
            $this->free();
        }
        return $res;
    }

    /**
     * Fetch a row of data into an array which is passed by reference
     *
     * The type of array returned can be controlled either by setting this
     * method's <var>$fetchmode</var> parameter or by changing the default
     * fetch mode setFetchMode() before calling this method.
     *
     * There are two options for standardizing the information returned
     * from databases, ensuring their values are consistent when changing
     * DBMS's.  These portability options can be turned on when creating a
     * new DB object or by using setOption().
     *
     *   + <var>DB_PORTABILITY_LOWERCASE</var>
     *     convert names of fields to lower case
     *
     *   + <var>DB_PORTABILITY_RTRIM</var>
     *     right trim the data
     *
     * @param array &$arr       the variable where the data should be placed
     * @param int   $fetchmode  the constant indicating how to format the data
     * @param int   $rownum     the row number to fetch (index starts at 0)
     *
     * @return mixed  DB_OK if a row is processed, NULL when the end of the
     *                 result set is reached or a DB_Error object on failure
     *
     * @see DB\Common::setOption(), DB\Common::setFetchMode()
     */
    function fetchInto(&$arr, $fetchmode = DB_FETCHMODE_DEFAULT, $rownum = null)
    {
        if ($fetchmode === DB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode === DB_FETCHMODE_OBJECT) {
            $fetchmode = DB_FETCHMODE_ASSOC;
            $object_class = $this->fetchmode_object_class;
        }
        if (is_null($rownum) && $this->limit_from !== null) {
            if ($this->row_counter === null) {
                $this->row_counter = $this->limit_from;
                // Skip rows
                if ($this->dbh->features['limit'] === false) {
                    $i = 0;
                    while ($i++ < $this->limit_from) {
                        $this->dbh->fetchInto($this->result, $arr, $fetchmode);
                    }
                }
            }
            if ($this->row_counter >= (
                    $this->limit_from + $this->limit_count))
            {
                if ($this->autofree) {
                    $this->free();
                }
                return null;
            }
            if ($this->dbh->features['limit'] === 'emulate') {
                $rownum = $this->row_counter;
            }

            $this->row_counter++;
        }
        $res = $this->dbh->fetchInto($this->result, $arr, $fetchmode, $rownum);
        if ($res === DB_OK) {
            if (isset($object_class)) {
                // default mode specified in the
                // DB\Common::fetchmode_object_class property
                if ($object_class == 'stdClass') {
                    $arr = (object) $arr;
                } else {
                    $arr = new $object_class($arr);
                }
            }
            return DB_OK;
        }
        if ($res == null && $this->autofree) {
            $this->free();
        }
        return $res;
    }

    /**
     * Get the the number of columns in a result set
     *
     * @return int  the number of columns.  A DB_Error object on failure.
     */
    function numCols()
    {
        return $this->dbh->numCols($this->result);
    }

    /**
     * Get the number of rows in a result set
     *
     * @return int  the number of rows.  A DB_Error object on failure.
     */
    function numRows()
    {
        if ($this->dbh->features['numrows'] === 'emulate'
            && $this->dbh->options['portability'] & DB_PORTABILITY_NUMROWS)
        {
            if ($this->dbh->features['prepare']) {
                $res = $this->dbh->query($this->query, $this->parameters);
            } else {
                $res = $this->dbh->query($this->query);
            }
            if (DB::isError($res)) {
                return $res;
            }
            $i = 0;
            while ($res->fetchInto($tmp, DB_FETCHMODE_ORDERED)) {
                $i++;
            }
            $count = $i;
        } else {
            $count = $this->dbh->numRows($this->result);
        }

        /* fbsql is checked for here because limit queries are implemented
         * using a TOP() function, which results in fbsql_num_rows still
         * returning the total number of rows that would have been returned,
         * rather than the real number. As a result, we'll just do the limit
         * calculations for fbsql in the same way as a database with emulated
         * limits. Unfortunately, we can't just do this in DB_fbsql::numRows()
         * because that only gets the result resource, rather than the full
         * DB_Result object. */
        if (($this->dbh->features['limit'] === 'emulate'
             && $this->limit_from !== null)
            || $this->dbh->phptype == 'fbsql') {
            $limit_count = is_null($this->limit_count) ? $count : $this->limit_count;
            if ($count < $this->limit_from) {
                $count = 0;
            } elseif ($count < ($this->limit_from + $limit_count)) {
                $count -= $this->limit_from;
            } else {
                $count = $limit_count;
            }
        }

        return $count;
    }

    /**
     * Get the next result if a batch of queries was executed
     *
     * @return bool  true if a new result is available or false if not
     */
    function nextResult()
    {
        return $this->dbh->nextResult($this->result);
    }

    /**
     * Frees the resources allocated for this result set
     *
     * @return bool  true on success.  A DB_Error object on failure.
     */
    function free()
    {
        $err = $this->dbh->freeResult($this->result);
        if (DB::isError($err)) {
            return $err;
        }
        $this->result = false;
        $this->statement = false;
        return true;
    }

    /**
     * @see DB\Common::tableInfo()
     * @deprecated Method deprecated some time before Release 1.2
     */
    function tableInfo($mode = null)
    {
        if (is_string($mode)) {
            return $this->dbh->raiseError(DB_ERROR_NEED_MORE_DATA);
        }
        return $this->dbh->tableInfo($this, $mode);
    }

    /**
     * Determine the query string that created this result
     *
     * @return string  the query string
     *
     * @since Method available since Release 1.7.0
     */
    function getQuery()
    {
        return $this->query;
    }

    /**
     * Tells which row number is currently being processed
     *
     * @return integer  the current row being looked at.  Starts at 1.
     */
    function getRowCounter()
    {
        return $this->row_counter;
    }
}
