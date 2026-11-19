<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Migration;

use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\ColumnInterface;

/**
 * This class is the connection a real migration runs on: it runs every call on a
 * Phalcon database adapter. It passes only arguments both Phalcon 4 and Phalcon 5
 * accept (arrays for bound parameters, no null schema), so the same code runs on
 * either stack.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Db\Migration
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AdapterConnection implements SchemaConnection
{
    /**
     * The Phalcon adapter the calls run on
     *
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * AdapterConnection constructor.
     *
     * @param AdapterInterface $adapter
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function execute(string $sql, array $placeholders = []): bool
    {
        return $this->adapter->execute($sql, $placeholders);
    }

    public function createTable(string $tableName, $schemaName, array $definition): bool
    {
        return $this->adapter->createTable($tableName, (string) $schemaName, $definition);
    }

    public function addColumn(string $tableName, $schemaName, ColumnInterface $column): bool
    {
        return $this->adapter->addColumn($tableName, (string) $schemaName, $column);
    }

    public function begin(): bool
    {
        return $this->adapter->begin();
    }

    public function commit(): bool
    {
        return $this->adapter->commit();
    }

    public function rollback(): bool
    {
        return $this->adapter->rollback();
    }

    public function query(string $sql, array $placeholders = [])
    {
        return $this->adapter->query($sql, $placeholders);
    }

    public function fetchAll(string $sql, int $fetchMode = \PDO::FETCH_ASSOC, array $placeholders = []): array
    {
        return $this->adapter->fetchAll($sql, $fetchMode, $placeholders);
    }

    public function fetchOne(string $sql, int $fetchMode = \PDO::FETCH_ASSOC, array $placeholders = []): array
    {
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
