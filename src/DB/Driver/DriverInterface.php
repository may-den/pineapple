<?php
namespace Pineapple\DB\Driver;

interface DriverInterface
{
    /**
     * Get a Pineapple DB error code from a driver-specific error code,
     * returning a standard 'generic error' if unmappable.
     *
     * @param string $code The driver error code to map to native
     *
     * @return int         The DB::DB_ERROR_* constant
     */
    public function getNativeErrorCode($code);
}
