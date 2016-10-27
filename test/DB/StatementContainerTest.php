<?php
namespace Pineapple\Test\DB;

use Pineapple\DB\StatementContainer;
use Pineapple\DB\Exception\StatementException;

use PHPUnit\Framework\TestCase;

use stdClass;

class StatementContainerTest extends TestCase
{
    private $callbackFired = false;
    private $contentsToCheck = null;

    public function testConstruct()
    {
        $statementContainer = new StatementContainer();
        $this->assertInstanceOf(StatementContainer::class, $statementContainer);
    }

    public function testConstructWithStatement()
    {
        $statementContainer = new StatementContainer(['fingy']);
        $this->assertEquals(['fingy'], $statementContainer->getStatement());
    }

    public function freeTrigger($data)
    {
        $this->assertEquals($this->contentsToCheck, $data);
        $this->callbackFired = true;
    }

    public function testConstructWithStatementAndFreeFunction()
    {
        $statement = $this->contentsToCheck = ['fingy'];
        $this->callbackFired = false;
        $statementContainer = new StatementContainer($statement, [$this, 'freeTrigger']);

        $this->assertEquals($statement, $statementContainer->getStatement());

        $statementContainer->freeStatement();
        $this->assertTrue($this->callbackFired);
    }

    public function testSetGetStatement()
    {
        $statement = $this->contentsToCheck = ['MAU50100'];
        $statementContainer = new StatementContainer();
        $statementContainer->setStatement($statement);
        $this->assertEquals($statement, $statementContainer->getStatement());
    }

    public function testSetGetStatementWithFreeFunction()
    {
        $statement = $this->contentsToCheck = ['MAU50100'];
        $this->callbackFired = false;
        $statementContainer = new StatementContainer();
        $statementContainer->setStatement($statement, [$this, 'freeTrigger']);
        $this->assertEquals($statement, $statementContainer->getStatement());
    }

    public function testSetStatementBadType()
    {
        $statementContainer = new StatementContainer();
        $this->expectException(StatementException::class);
        $this->expectExceptionCode(StatementException::UNHANDLED_TYPE);
        $statementContainer->setStatement('pointless');
    }

    public function testFreeStatement()
    {
        $statementContainer = new StatementContainer(['bobbins']);
        $statementContainer->freeStatement();
        $this->expectException(StatementException::class);
        $this->expectExceptionCode(StatementException::NO_STATEMENT);
        $statementContainer->getStatement();
    }

    public function testFreeStatementWithResource()
    {
        $statementContainer = new StatementContainer(fopen('php://stdout', 'a'));
        $statementContainer->freeStatement();
        $this->expectException(StatementException::class);
        $this->expectExceptionCode(StatementException::NO_STATEMENT);
        $statementContainer->getStatement();
    }

    public function testFreeStatementWithNoStatement()
    {
        $statementContainer = new StatementContainer();
        $this->expectException(StatementException::class);
        $this->expectExceptionCode(StatementException::NO_STATEMENT);
        $statementContainer->freeStatement();
    }

    public function testGetStatementTypeObject()
    {
        $statementContainer = new StatementContainer((object) ['cabbage']);
        $this->assertEquals(
            [
                'type' => 'object',
                'class' => stdClass::class,
            ],
            $statementContainer->getStatementType()
        );
    }

    public function testGetStatementTypeResource()
    {
        $statementContainer = new StatementContainer(fopen('php://stdout', 'a'));
        $this->assertEquals(
            ['type' => 'resource'],
            $statementContainer->getStatementType()
        );
    }

    public function testGetStatementTypeArray()
    {
        $statementContainer = new StatementContainer(['kale']);
        $this->assertEquals(
            ['type' => 'array'],
            $statementContainer->getStatementType()
        );
    }

    public function testGetStatementNoStatement()
    {
        $statementContainer = new StatementContainer();
        $this->expectException(StatementException::class);
        $this->expectExceptionCode(StatementException::NO_STATEMENT);
        $statementContainer->getStatementType();
    }
}
