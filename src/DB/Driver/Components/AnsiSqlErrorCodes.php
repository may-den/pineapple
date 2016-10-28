<?php
namespace Pineapple\DB\Driver\Components;

use Pineapple\DB;

/**
 * ANSI SQL error codes, taken from the PHP PDO source code.
 *
 * @author     Rob Andrews <rob@aphlor.org>
 * @license    BSD-2-Clause
 * @package    Database
 * @version    Introduced in Pineapple 0.3.0
 */
trait AnsiSqlErrorCodes
{
    /**
     * A mapping of ANSI SQL error codes to Pineapple error codes
     * @var array
     */
    private static $errorcodeMap = [
        '00000' => DB::DB_OK, // No error
        '01004' => DB::DB_ERROR_TRUNCATED, // String data, right truncated
        '01P01' => DB::DB_ERROR_UNSUPPORTED, // Deprecated feature
        '01S00' => DB::DB_ERROR_INVALID_DSN, // Invalid connection string attribute
        '01S07' => DB::DB_ERROR_TRUNCATED, // Fractional truncation
        '01S09' => DB::DB_ERROR_SYNTAX, // Invalid keyword
        '03000' => DB::DB_ERROR_SYNTAX, // Sql statement not yet complete
        '07006' => DB::DB_ERROR_INVALID, // Restricted data type attribute violation
        '08001' => DB::DB_ERROR_CONNECT_FAILED, // Client unable to establish connection
        '08004' => DB::DB_ERROR_CONNECT_FAILED, // Server rejected the connection
        '08006' => DB::DB_ERROR_CONNECT_FAILED, // Connection failure
        '08007' => DB::DB_ERROR_CONNECT_FAILED, // Connection failure during transaction
        '08S01' => DB::DB_ERROR_CONNECT_FAILED, // Communication link failure
        '0A000' => DB::DB_ERROR_NOT_CAPABLE, // Feature not supported
        '21S01' => DB::DB_ERROR_MISMATCH, // Insert value list does not match column list
        '21S02' => DB::DB_ERROR_MISMATCH, // Degree of derived table does not match column list
        '22001' => DB::DB_ERROR_TRUNCATED, // String data, right truncated
        '22003' => DB::DB_ERROR_INVALID_NUMBER, // Numeric value out of range
        '22004' => DB::DB_ERROR_INVALID, // Null value not allowed
        '22005' => DB::DB_ERROR_INVALID, // Error in assignment
        '22007' => DB::DB_ERROR_INVALID_DATE, // Invalid datetime format
        '22008' => DB::DB_ERROR_INVALID_DATE, // Datetime field overflow
        '22009' => DB::DB_ERROR_INVALID_DATE, // Invalid time zone displacement value
        '2200F' => DB::DB_ERROR_INVALID, // Zero length character string
        '2200G' => DB::DB_ERROR_INVALID, // Most specific type mismatch
        '22010' => DB::DB_ERROR_INVALID, // Invalid indicator parameter value
        '22012' => DB::DB_ERROR_DIVZERO, // Division by zero
        '22023' => DB::DB_ERROR_INVALID, // Invalid parameter value
        '22026' => DB::DB_ERROR_INVALID, // String data, length mismatch
        '23000' => DB::DB_ERROR_CONSTRAINT, // Integrity constraint violation
        '23001' => DB::DB_ERROR_CONSTRAINT, // Restrict violation
        '23502' => DB::DB_ERROR_CONSTRAINT_NOT_NULL, // Not null violation
        '23503' => DB::DB_ERROR_CONSTRAINT, // Foreign key violation
        '23505' => DB::DB_ERROR_CONSTRAINT, // Unique violation
        '23514' => DB::DB_ERROR_CONSTRAINT, // Check violation
        '40002' => DB::DB_ERROR_CONSTRAINT, // Transaction integrity constraint violation
        '42000' => DB::DB_ERROR_SYNTAX, // Syntax error or access violation
        '42601' => DB::DB_ERROR_SYNTAX, // Syntax error
        '42P01' => DB::DB_ERROR_NOSUCHTABLE, // Undefined table
        '57P03' => DB::DB_ERROR_CONNECT_FAILED, // Cannot connect now
        'HYC00' => DB::DB_ERROR_NOT_CAPABLE, // Optional feature not implemented
        'HYT01' => DB::DB_ERROR_CONNECT_FAILED, // Connection timeout expired
        'IM001' => DB::DB_ERROR_UNSUPPORTED, // Driver does not support this function
        'IM002' => DB::DB_ERROR_INVALID_DSN, // Data source name not found and no default driver specified
        'IM010' => DB::DB_ERROR_INVALID_DSN, // Data source name too long
        'IM011' => DB::DB_ERROR_INVALID_DSN, // Driver name too long
        'IM012' => DB::DB_ERROR_INVALID_DSN, // DRIVER keyword syntax error
        'IM014' => DB::DB_ERROR_INVALID_DSN, // Invalid name of File DSN
    ];

    /**
     * Get a Pineapple DB error code from a driver-specific error code,
     * returning a standard 'generic error' if unmappable.
     *
     * @param string $code The driver error code to map to native
     * @return int         The DB::DB_ERROR_* constant
     */
    public function getNativeErrorCode($code)
    {
        if (isset(self::$errorcodeMap[$code])) {
            return self::$errorcodeMap[$code];
        }
        return DB::DB_ERROR;
    }

    /**
     * these are unmapped to Pineapple error codes, and as such will fall to
     * DB::DB_ERROR. they are preserved here for posterity/reference.
     *
     * '01000', // Warning
     * '01001', // Cursor operation conflict
     * '01002', // Disconnect error
     * '01003', // NULL value eliminated in set function
     * '01006', // Privilege not revoked
     * '01007', // Privilege not granted
     * '01008', // Implicit zero bit padding
     * '0100C', // Dynamic result sets returned
     * '01S01', // Error in row
     * '01S02', // Option value changed
     * '01S06', // Attempt to fetch before the result set returned the first rowset
     * '01S08', // Error saving File DSN
     * '02000', // No data
     * '02001', // No additional dynamic result sets returned
     * '07002', // COUNT field incorrect
     * '07005', // Prepared statement not a cursor-specification
     * '07009', // Invalid descriptor index
     * '07S01', // Invalid use of default parameter
     * '08000', // Connection exception
     * '08002', // Connection name in use
     * '08003', // Connection does not exist
     * '09000', // Triggered action exception
     * '0B000', // Invalid transaction initiation
     * '0F000', // Locator exception
     * '0F001', // Invalid locator specification
     * '0L000', // Invalid grantor
     * '0LP01', // Invalid grant operation
     * '0P000', // Invalid role specification
     * '21000', // Cardinality violation
     * '22000', // Data exception
     * '22002', // Indicator variable required but not supplied
     * '2200B', // Escape character conflict
     * '2200C', // Invalid use of escape character
     * '2200D', // Invalid escape octet
     * '22011', // Substring error
     * '22015', // Interval field overflow
     * '22018', // Invalid character value for cast specification
     * '22019', // Invalid escape character
     * '2201B', // Invalid regular expression
     * '2201E', // Invalid argument for logarithm
     * '2201F', // Invalid argument for power function
     * '2201G', // Invalid argument for width bucket function
     * '22020', // Invalid limit value
     * '22021', // Character not in repertoire
     * '22022', // Indicator overflow
     * '22024', // Unterminated c string
     * '22025', // Invalid escape sequence
     * '22027', // Trim error
     * '2202E', // Array subscript error
     * '22P01', // Floating point exception
     * '22P02', // Invalid text representation
     * '22P03', // Invalid binary representation
     * '22P04', // Bad copy file format
     * '22P05', // Untranslatable character
     * '24000', // Invalid cursor state
     * '25000', // Invalid transaction state
     * '25001', // Active sql transaction
     * '25002', // Branch transaction already active
     * '25003', // Inappropriate access mode for branch transaction
     * '25004', // Inappropriate isolation level for branch transaction
     * '25005', // No active sql transaction for branch transaction
     * '25006', // Read only sql transaction
     * '25007', // Schema and data statement mixing not supported
     * '25008', // Held cursor requires same isolation level
     * '25P01', // No active sql transaction
     * '25P02', // In failed sql transaction
     * '25S01', // Transaction state
     * '25S02', // Transaction is still active
     * '25S03', // Transaction is rolled back
     * '26000', // Invalid sql statement name
     * '27000', // Triggered data change violation
     * '28000', // Invalid authorization specification
     * '2B000', // Dependent privilege descriptors still exist
     * '2BP01', // Dependent objects still exist
     * '2D000', // Invalid transaction termination
     * '2F000', // Sql routine exception
     * '2F002', // Modifying sql data not permitted
     * '2F003', // Prohibited sql statement attempted
     * '2F004', // Reading sql data not permitted
     * '2F005', // Function executed no return statement
     * '34000', // Invalid cursor name
     * '38000', // External routine exception
     * '38001', // Containing sql not permitted
     * '38002', // Modifying sql data not permitted
     * '38003', // Prohibited sql statement attempted
     * '38004', // Reading sql data not permitted
     * '39000', // External routine invocation exception
     * '39001', // Invalid sqlstate returned
     * '39004', // Null value not allowed // n.b. this is NOT DB_ERROR_CONSTRAINT_NOT_NULL
     * '39P01', // Trigger protocol violated
     * '39P02', // Srf protocol violated
     * '3B000', // Savepoint exception
     * '3B001', // Invalid savepoint specification
     * '3C000', // Duplicate cursor name
     * '3D000', // Invalid catalog name
     * '3F000', // Invalid schema name
     * '40000', // Transaction rollback
     * '40001', // Serialization failure
     * '40003', // Statement completion unknown
     * '40P01', // Deadlock detected
     * '42501', // Insufficient privilege
     * '42602', // Invalid name
     * '42611', // Invalid column definition
     * '42622', // Name too long
     * '42701', // Duplicate column
     * '42702', // Ambiguous column
     * '42703', // Undefined column
     * '42704', // Undefined object
     * '42710', // Duplicate object
     * '42712', // Duplicate alias
     * '42723', // Duplicate function
     * '42725', // Ambiguous function
     * '42803', // Grouping error
     * '42804', // Datatype mismatch
     * '42809', // Wrong object type
     * '42830', // Invalid foreign key
     * '42846', // Cannot coerce
     * '42883', // Undefined function
     * '42939', // Reserved name
     * '42P02', // Undefined parameter
     * '42P03', // Duplicate cursor
     * '42P04', // Duplicate database
     * '42P05', // Duplicate prepared statement
     * '42P06', // Duplicate schema
     * '42P07', // Duplicate table
     * '42P08', // Ambiguous parameter
     * '42P09', // Ambiguous alias
     * '42P10', // Invalid column reference
     * '42P11', // Invalid cursor definition
     * '42P12', // Invalid database definition
     * '42P13', // Invalid function definition
     * '42P14', // Invalid prepared statement definition
     * '42P15', // Invalid schema definition
     * '42P16', // Invalid table definition
     * '42P17', // Invalid object definition
     * '42P18', // Indeterminate datatype
     * '42S01', // Base table or view already exists
     * '42S02', // Base table or view not found
     * '42S11', // Index already exists
     * '42S12', // Index not found
     * '42S21', // Column already exists
     * '42S22', // Column not found
     * '44000', // WITH CHECK OPTION violation
     * '53000', // Insufficient resources
     * '53100', // Disk full
     * '53200', // Out of memory
     * '53300', // Too many connections
     * '54000', // Program limit exceeded
     * '54001', // Statement too complex
     * '54011', // Too many columns
     * '54023', // Too many arguments
     * '55000', // Object not in prerequisite state
     * '55006', // Object in use
     * '55P02', // Cant change runtime param
     * '55P03', // Lock not available
     * '57000', // Operator intervention
     * '57014', // Query canceled
     * '57P01', // Admin shutdown
     * '57P02', // Crash shutdown
     * '58030', // Io error
     * '58P01', // Undefined file
     * '58P02', // Duplicate file
     * 'F0000', // Config file error
     * 'F0001', // Lock file exists
     * 'HY000', // General error
     * 'HY001', // Memory allocation error
     * 'HY003', // Invalid application buffer type
     * 'HY004', // Invalid SQL data type
     * 'HY007', // Associated statement is not prepared
     * 'HY008', // Operation canceled
     * 'HY009', // Invalid use of null pointer
     * 'HY010', // Function sequence error
     * 'HY011', // Attribute cannot be set now
     * 'HY012', // Invalid transaction operation code
     * 'HY013', // Memory management error
     * 'HY014', // Limit on the number of handles exceeded
     * 'HY015', // No cursor name available
     * 'HY016', // Cannot modify an implementation row descriptor
     * 'HY017', // Invalid use of an automatically allocated descriptor handle
     * 'HY018', // Server declined cancel request
     * 'HY019', // Non-character and non-binary data sent in pieces
     * 'HY020', // Attempt to concatenate a null value
     * 'HY021', // Inconsistent descriptor information
     * 'HY024', // Invalid attribute value
     * 'HY090', // Invalid string or buffer length
     * 'HY091', // Invalid descriptor field identifier
     * 'HY092', // Invalid attribute/option identifier
     * 'HY093', // Invalid parameter number
     * 'HY095', // Function type out of range
     * 'HY096', // Invalid information type
     * 'HY097', // Column type out of range
     * 'HY098', // Scope type out of range
     * 'HY099', // Nullable type out of range
     * 'HY100', // Uniqueness option type out of range
     * 'HY101', // Accuracy option type out of range
     * 'HY103', // Invalid retrieval code
     * 'HY104', // Invalid precision or scale value
     * 'HY105', // Invalid parameter type
     * 'HY106', // Fetch type out of range
     * 'HY107', // Row value out of range
     * 'HY109', // Invalid cursor position
     * 'HY110', // Invalid driver completion
     * 'HY111', // Invalid bookmark value
     * 'HYT00', // Timeout expired
     * 'IM003', // Specified driver could not be loaded
     * 'IM004', // Driver's SQLAllocHandle on SQL_HANDLE_ENV failed
     * 'IM005', // Driver's SQLAllocHandle on SQL_HANDLE_DBC failed
     * 'IM006', // Driver's SQLSetConnectAttr failed
     * 'IM007', // No data source or driver specified; dialog prohibited
     * 'IM008', // Dialog failed
     * 'IM009', // Unable to load translation DLL
     * 'IM013', // Trace file error
     * 'IM015', // Corrupt file data source
     * 'P0000', // Plpgsql error
     * 'P0001', // Raise exception
     * 'XX000', // Internal error
     * 'XX001', // Data corrupted
     * 'XX002', // Index corrupted
     */
}
