<?php
namespace Pineapple\DB;

use Pineapple\DB\Exception\StatementException;

/**
 * A container for objects and resources pertaining to statement handles.
 *
 * @author     Rob Andrews <rob@aphlor.org>
 * @copyright  BSD-2-Clause
 * @package    Database
 * @version    Introduced in Pineapple 0.3.0
 */
class StatementContainer
{
    // @var mixed The statement handle (could be object, resource, array)
    private $statement = null;
    // @var callable A function to call to destruct a statement handle (useful for mysql_*)
    private $freeFunction = null;

    /**
     * constructor
     *
     * @param mixed    $statement    The statement handle (can be object, resource, array)
     * @param callable $freeFunction A callback to destroy a result handle/object
     */
    public function __construct($statement = null, $freeFunction = null)
    {
        if ($statement !== null) {
            $this->setStatement($statement, $freeFunction);
        }
    }

    /**
     * Return the statement object/resource/array.
     *
     * @return mixed
     * @throws StatementException
     */
    public function getStatement()
    {
        if (!isset($this->statement) || ($this->statement === null)) {
            throw new StatementException('No statement set', StatementException::NO_STATEMENT);
        }

        return $this->statement;
    }

    /**
     * Set the statement object/resource/array the container should hold.
     *
     * @param mixed    $statement    The statement handle (can be object, resource, array)
     * @param callable $freeFunction A callback to destroy a result handle/object
     * @return null
     */
    public function setStatement($statement, $freeFunction = null)
    {
        switch (gettype($statement)) {
            case 'object':
            case 'resource':
            case 'array':
                // this is fine.
                break;

            default:
                throw new StatementException(
                    'We do not know how to deal with this type of statement handle',
                    StatementException::UNHANDLED_TYPE
                );
                // unreachable.
                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd
        }

        $this->statement = $statement;

        if ($freeFunction !== null) {
            $this->freeFunction = $freeFunction;
        }
    }

    /**
     * Destroy the statement by unsetting or calling the result free callable
     *
     * @return null
     * @throws StatementException
     */
    public function freeStatement()
    {
        if (!isset($this->statement) || ($this->statement === null)) {
            throw new StatementException('No statement set', StatementException::NO_STATEMENT);
        }

        switch (gettype($this->statement)) {
            case 'object':
            case 'array':
            case 'resource':
                if (($this->freeFunction !== null) && is_callable($this->freeFunction)) {
                    call_user_func($this->freeFunction, $this->statement);
                    unset($this->statement);
                    return;
                }
                unset($this->statement);
                break;

            default:
                // because we're rigid about what we accept, this is a "future expansion" fault
                // @codeCoverageIgnoreStart
                throw new StatementException(
                    'Stored statement is not a type we are experienced with dealing with',
                    StatementException::UNHANDLED_TYPE
                );
                break;
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Call the destroy function for the contained result.
     *
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        if (isset($this->statement) && ($this->statement !== null)) {
            $this->freeStatement();
        }
    }

    /**
     * Return the statement type, and if an object, the class name
     *
     * @return array
     * @throws StatementException
     */
    public function getStatementType()
    {
        if (!isset($this->statement) || ($this->statement === null)) {
            throw new StatementException('No statement set', StatementException::NO_STATEMENT);
        }

        switch (gettype($this->statement)) {
            case 'object':
                return [
                    'type' => 'object',
                    'class' => get_class($this->statement),
                ];
                // we've returned above, so this isn't run
                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd

            case 'resource':
                return ['type' => 'resource'];
                // we've returned above, so this isn't run
                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd

            case 'array':
                return ['type' => 'array'];
                // we've returned above, so this isn't run
                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd

            default:
                // because we're rigid about what we accept, this is a "future expansion" fault
                // @codeCoverageIgnoreStart
                throw new StatementException(
                    'Stored statement is not a type we are experienced with dealing with',
                    StatementException::UNHANDLED_TYPE
                );
                break;
                // @codeCoverageIgnoreEnd
        }
    }
}
