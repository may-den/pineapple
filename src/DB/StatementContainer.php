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
        if (!in_array(gettype($statement), ['object', 'resource', 'array'])) {
            throw new StatementException(
                'We do not know how to deal with this type of statement handle',
                StatementException::UNHANDLED_TYPE
            );
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

        if (($this->freeFunction !== null) && is_callable($this->freeFunction)) {
            call_user_func($this->freeFunction, $this->statement);
            unset($this->statement);
            return;
        }
        unset($this->statement);
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

            case 'resource':
                return ['type' => 'resource'];

            case 'array':
                return ['type' => 'array'];
        }
    }
}
