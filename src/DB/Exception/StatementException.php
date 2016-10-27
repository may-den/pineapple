<?php
namespace Pineapple\DB\Exception;

use Exception;

class StatementException extends Exception
{
    const NO_STATEMENT = -128;
    const UNHANDLED_TYPE = -129;
}
