<?php
namespace Pineapple\DB\Exception;

use Exception;

/**
 * Exceptions which within the StatementContainer class
 *
 * @author     Rob Andrews <rob@aphlor.org>
 * @license    BSD-2-Clause
 * @package    Database
 * @version    Introduced in Pineapple 0.3.0
 */
class StatementException extends Exception
{
    const NO_STATEMENT = -128;
    const UNHANDLED_TYPE = -129;
}
