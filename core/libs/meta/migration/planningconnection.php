<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta\Migration;

use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\ColumnInterface;
use Phalcon\Db\DialectInterface;
use Phalcon\Db\IndexInterface;
use Phalcon\Db\RawValue;
use Phalcon\Db\ReferenceInterface;

/**
 * This class is the connection a dry-run migration runs on. It decorates the real
 * connection: reads (describe, fetch, query, exists checks) go to the database, and
 * every write (execute and the DDL helpers) is rendered to SQL with the connection's
 * dialect and added to a Plan instead of being run. Transactions are not opened.
 * The data-write helpers (insert, update, delete) are not part of a schema plan and
 * throw; SQL passed to execute() is planned as it is. Reads must be SELECT, WITH or
 * SHOW statements, and the raw PDO handle is not handed out, so nothing bypasses the plan.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Meta\Migration
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PlanningConnection implements AdapterInterface
{
    /**
     * The real connection reads go to
     *
     * @var AdapterInterface
     */
    private $connection;

    /**
     * The plan the writes are added to
     *
     * @var Plan
     */
    private $plan;

    /**
     * PlanningConnection constructor.
     *
     * @param AdapterInterface $connection The real connection
     * @param Plan $plan The plan writes are added to
     */
    public function __construct(AdapterInterface $connection, Plan $plan)
    {
        $this->connection = $connection;
        $this->plan = $plan;
    }

    /**
     * This function adds the statements of a dialect script (separated by ";"), the way
     * the PDO adapters run a multi-statement createTable or modifyColumn.
     *
     * @param string $sql
     * @return bool
     */
    private function plan(string $sql): bool
    {
        foreach (explode(';', $sql) as $statement) {
            $this->plan->add($statement);
        }
        return true;
    }

    /**
     * This function rejects SQL sent down a read path that is not a read, so a write
     * cannot reach the database during a dry run.
     *
     * @param string $sql
     * @return void
     * @throws \LogicException
     */
    private function assertRead(string $sql)
    {
        if (!preg_match('/^\s*(SELECT|WITH|SHOW)\b/i', $sql)) {
            throw new \LogicException('A dry-run migration only reads through query and fetch methods');
        }
    }

    /**
     * This function rejects a data write: a schema plan has none.
     *
     * @param string $operation
     * @return void
     * @throws \LogicException
     */
    private function rejectDataWrite(string $operation)
    {
        throw new \LogicException("A dry-run migration does not run data writes ($operation)");
    }

    // Writes: planned, not run

    public function execute(string $sqlStatement, $placeholders = null, $dataTypes = null): bool
    {
        $this->plan->add($sqlStatement);
        return true;
    }

    public function createTable(string $tableName, string $schemaName, array $definition): bool
    {
        return $this->plan($this->getDialect()->createTable($tableName, $schemaName, $definition));
    }

    public function dropTable(string $tableName, string $schemaName = null, bool $ifExists = true): bool
    {
        return $this->plan($this->getDialect()->dropTable($tableName, $schemaName, $ifExists));
    }

    public function createView(string $viewName, array $definition, string $schemaName = null): bool
    {
        return $this->plan($this->getDialect()->createView($viewName, $definition, $schemaName));
    }

    public function dropView(string $viewName, string $schemaName = null, bool $ifExists = true): bool
    {
        return $this->plan($this->getDialect()->dropView($viewName, $schemaName, $ifExists));
    }

    public function addColumn(string $tableName, string $schemaName, ColumnInterface $column): bool
    {
        return $this->plan($this->getDialect()->addColumn($tableName, $schemaName, $column));
    }

    public function modifyColumn(
        string $tableName,
        string $schemaName,
        ColumnInterface $column,
        ColumnInterface $currentColumn = null
    ): bool {
        return $this->plan($this->getDialect()->modifyColumn($tableName, $schemaName, $column, $currentColumn));
    }

    public function dropColumn(string $tableName, string $schemaName, string $columnName): bool
    {
        return $this->plan($this->getDialect()->dropColumn($tableName, $schemaName, $columnName));
    }

    public function addIndex(string $tableName, string $schemaName, IndexInterface $index): bool
    {
        return $this->plan($this->getDialect()->addIndex($tableName, $schemaName, $index));
    }

    public function dropIndex(string $tableName, string $schemaName, string $indexName): bool
    {
        return $this->plan($this->getDialect()->dropIndex($tableName, $schemaName, $indexName));
    }

    public function addPrimaryKey(string $tableName, string $schemaName, IndexInterface $index): bool
    {
        return $this->plan($this->getDialect()->addPrimaryKey($tableName, $schemaName, $index));
    }

    public function dropPrimaryKey(string $tableName, string $schemaName): bool
    {
        return $this->plan($this->getDialect()->dropPrimaryKey($tableName, $schemaName));
    }

    public function addForeignKey(string $tableName, string $schemaName, ReferenceInterface $reference): bool
    {
        return $this->plan($this->getDialect()->addForeignKey($tableName, $schemaName, $reference));
    }

    public function dropForeignKey(string $tableName, string $schemaName, string $referenceName): bool
    {
        return $this->plan($this->getDialect()->dropForeignKey($tableName, $schemaName, $referenceName));
    }

    // Data writes: rejected

    public function insert(string $table, array $values, $fields = null, $dataTypes = null): bool
    {
        $this->rejectDataWrite('insert');
    }

    public function insertAsDict(string $table, $data, $dataTypes = null): bool
    {
        $this->rejectDataWrite('insert');
    }

    public function update(string $table, $fields, $values, $whereCondition = null, $dataTypes = null): bool
    {
        $this->rejectDataWrite('update');
    }

    public function updateAsDict(string $table, $data, $whereCondition = null, $dataTypes = null): bool
    {
        $this->rejectDataWrite('update');
    }

    public function delete($table, $whereCondition = null, $placeholders = null, $dataTypes = null): bool
    {
        $this->rejectDataWrite('delete');
    }

    // Transactions: nothing runs, so none is opened

    public function begin(bool $nesting = true): bool
    {
        return true;
    }

    public function commit(bool $nesting = true): bool
    {
        return true;
    }

    public function rollback(bool $nesting = true): bool
    {
        return true;
    }

    public function createSavepoint(string $name): bool
    {
        return true;
    }

    public function releaseSavepoint(string $name): bool
    {
        return true;
    }

    public function rollbackSavepoint(string $name): bool
    {
        return true;
    }

    public function isUnderTransaction(): bool
    {
        return false;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function lastInsertId($sequenceName = null)
    {
        return false;
    }

    // Reads and helpers: the real connection

    public function query(string $sqlStatement, $placeholders = null, $dataTypes = null)
    {
        $this->assertRead($sqlStatement);
        return $this->connection->query(...func_get_args());
    }

    public function fetchAll(string $sqlQuery, int $fetchMode = 2, $placeholders = null): array
    {
        $this->assertRead($sqlQuery);
        return $this->connection->fetchAll(...func_get_args());
    }

    public function fetchOne(string $sqlQuery, int $fetchMode = 2, $placeholders = null): array
    {
        $this->assertRead($sqlQuery);
        // The PDO adapters return false for no row, which the interface's array type rejects here.
        $row = $this->connection->fetchOne(...func_get_args());
        return is_array($row) ? $row : array();
    }

    public function fetchColumn(string $sqlQuery, array $placeholders = null, $column = 0)
    {
        $this->assertRead($sqlQuery);
        return $this->connection->fetchColumn(...func_get_args());
    }

    public function describeColumns(string $table, string $schema = null): array
    {
        return $this->connection->describeColumns(...func_get_args());
    }

    public function describeIndexes(string $table, string $schema = null): array
    {
        return $this->connection->describeIndexes(...func_get_args());
    }

    public function describeReferences(string $table, string $schema = null): array
    {
        return $this->connection->describeReferences(...func_get_args());
    }

    public function tableExists(string $tableName, string $schemaName = null): bool
    {
        return $this->connection->tableExists(...func_get_args());
    }

    public function viewExists(string $viewName, string $schemaName = null): bool
    {
        return $this->connection->viewExists(...func_get_args());
    }

    public function tableOptions(string $tableName, string $schemaName = null): array
    {
        return $this->connection->tableOptions(...func_get_args());
    }

    public function listTables(string $schemaName = null): array
    {
        return $this->connection->listTables(...func_get_args());
    }

    public function listViews(string $schemaName = null): array
    {
        return $this->connection->listViews(...func_get_args());
    }

    public function escapeIdentifier($identifier): string
    {
        return $this->connection->escapeIdentifier($identifier);
    }

    public function escapeString(string $str): string
    {
        return $this->connection->escapeString($str);
    }

    public function forUpdate(string $sqlQuery): string
    {
        return $this->connection->forUpdate($sqlQuery);
    }

    public function sharedLock(string $sqlQuery): string
    {
        return $this->connection->sharedLock($sqlQuery);
    }

    public function limit(string $sqlQuery, int $number): string
    {
        return $this->connection->limit($sqlQuery, $number);
    }

    public function getColumnDefinition(ColumnInterface $column): string
    {
        return $this->connection->getColumnDefinition($column);
    }

    public function getColumnList($columnList): string
    {
        return $this->connection->getColumnList($columnList);
    }

    public function getDialect(): DialectInterface
    {
        return $this->connection->getDialect();
    }

    public function getDialectType(): string
    {
        return $this->connection->getDialectType();
    }

    public function getType(): string
    {
        return $this->connection->getType();
    }

    public function getDefaultIdValue(): RawValue
    {
        return $this->connection->getDefaultIdValue();
    }

    public function getDefaultValue(): RawValue
    {
        return $this->connection->getDefaultValue();
    }

    public function supportSequences(): bool
    {
        return $this->connection->supportSequences();
    }

    public function supportsDefaultValue(): bool
    {
        return $this->connection->supportsDefaultValue();
    }

    public function useExplicitIdValue(): bool
    {
        return $this->connection->useExplicitIdValue();
    }

    public function connect(array $descriptor = null): bool
    {
        return $this->connection->connect(...func_get_args());
    }

    public function close(): bool
    {
        return $this->connection->close();
    }

    public function getConnectionId(): string
    {
        return $this->connection->getConnectionId();
    }

    public function getDescriptor(): array
    {
        return $this->connection->getDescriptor();
    }

    public function getInternalHandler(): \PDO
    {
        throw new \LogicException('A dry-run migration does not hand out the raw database handle');
    }

    public function getNestedTransactionSavepointName(): string
    {
        return $this->connection->getNestedTransactionSavepointName();
    }

    public function isNestedTransactionsWithSavepoints(): bool
    {
        return $this->connection->isNestedTransactionsWithSavepoints();
    }

    public function setNestedTransactionsWithSavepoints(bool $nestedTransactionsWithSavepoints): AdapterInterface
    {
        return $this;
    }

    public function getRealSQLStatement(): string
    {
        return $this->connection->getRealSQLStatement();
    }

    public function getSQLStatement(): string
    {
        return $this->connection->getSQLStatement();
    }

    public function getSQLBindTypes(): array
    {
        return $this->connection->getSQLBindTypes();
    }

    public function getSQLVariables(): array
    {
        return $this->connection->getSQLVariables();
    }
}
