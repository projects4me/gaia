<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta\Migration;

use Gaia\Db\Migration\SchemaConnection;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\ColumnInterface;

/**
 * This class is the connection a dry-run migration runs on. It decorates the real
 * Phalcon adapter: reads (describe, fetch, query, exists checks) go to the database,
 * and every write (execute and the DDL helpers) is rendered to SQL with the adapter's
 * dialect and added to a Plan instead of being run. Transactions are not opened.
 * Reads must be SELECT, WITH or SHOW statements, so nothing bypasses the plan.
 * It implements Gaia's SchemaConnection, not Phalcon's AdapterInterface, so it does
 * not depend on the Phalcon version's adapter signatures.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Meta\Migration
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PlanningConnection implements SchemaConnection
{
    /**
     * The real adapter reads go to
     *
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * The plan the writes are added to
     *
     * @var Plan
     */
    private $plan;

    /**
     * PlanningConnection constructor.
     *
     * @param AdapterInterface $adapter The real adapter
     * @param Plan $plan The plan writes are added to
     */
    public function __construct(AdapterInterface $adapter, Plan $plan)
    {
        $this->adapter = $adapter;
        $this->plan = $plan;
    }

    /**
     * This function adds the statements of a dialect script (separated by ";"), the way
     * the PDO adapters run a multi-statement createTable.
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

    // Writes: planned, not run

    public function execute(string $sql, array $placeholders = []): bool
    {
        // A planned statement is shown as it is, so it cannot carry bound values.
        if (!empty($placeholders)) {
            throw new \LogicException('A dry-run migration cannot plan a statement with placeholders');
        }
        $this->plan->add($sql);
        return true;
    }

    public function createTable(string $tableName, $schemaName, array $definition): bool
    {
        return $this->plan($this->adapter->getDialect()->createTable($tableName, (string) $schemaName, $definition));
    }

    public function addColumn(string $tableName, $schemaName, ColumnInterface $column): bool
    {
        return $this->plan($this->adapter->getDialect()->addColumn($tableName, (string) $schemaName, $column));
    }

    // Transactions: nothing runs, so none is opened

    public function begin(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollback(): bool
    {
        return true;
    }

    // Reads: the real adapter

    public function query(string $sql, array $placeholders = [])
    {
        $this->assertRead($sql);
        return $this->adapter->query($sql, $placeholders);
    }

    public function fetchAll(string $sql, int $fetchMode = \PDO::FETCH_ASSOC, array $placeholders = []): array
    {
        $this->assertRead($sql);
        return $this->adapter->fetchAll($sql, $fetchMode, $placeholders);
    }

    public function fetchOne(string $sql, int $fetchMode = \PDO::FETCH_ASSOC, array $placeholders = []): array
    {
        $this->assertRead($sql);
        // The PDO adapters return false for no row.
        $row = $this->adapter->fetchOne($sql, $fetchMode, $placeholders);
        return is_array($row) ? $row : array();
    }

    public function describeColumns(string $tableName): array
    {
        return $this->adapter->describeColumns($tableName);
    }

    public function describeIndexes(string $tableName): array
    {
        return $this->adapter->describeIndexes($tableName);
    }

    public function tableExists(string $tableName): bool
    {
        return $this->adapter->tableExists($tableName);
    }

    public function viewExists(string $viewName): bool
    {
        return $this->adapter->viewExists($viewName);
    }

    public function escapeIdentifier(string $identifier): string
    {
        return $this->adapter->escapeIdentifier($identifier);
    }

    public function escapeString(string $value): string
    {
        return $this->adapter->escapeString($value);
    }
}
