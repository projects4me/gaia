<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Migration;

use Phalcon\Db\ColumnInterface;

/**
 * This interface is the database connection a schema migration runs on: the writes
 * (execute and the DDL helpers), the transaction calls and the reads the migration
 * layer (Migration, its driver and the migration handlers) makes, and nothing else.
 * It is Gaia's own, so its signatures do not change with the Phalcon version: an
 * AdapterConnection runs the calls on a Phalcon adapter, and a PlanningConnection
 * (a dry run) adds the writes to a plan instead of running them.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Db\Migration
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface SchemaConnection
{
    /**
     * This function runs a statement that returns no rows.
     *
     * @param string $sql
     * @param array $placeholders Bound parameters
     * @return bool
     */
    public function execute(string $sql, array $placeholders = []): bool;

    /**
     * This function creates a table from a Phalcon table definition (columns, indexes, options).
     *
     * @param string $tableName
     * @param string|null $schemaName
     * @param array $definition
     * @return bool
     */
    public function createTable(string $tableName, $schemaName, array $definition): bool;

    /**
     * This function adds a column to a table.
     *
     * @param string $tableName
     * @param string|null $schemaName
     * @param ColumnInterface $column
     * @return bool
     */
    public function addColumn(string $tableName, $schemaName, ColumnInterface $column): bool;

    /**
     * This function starts a transaction.
     *
     * @return bool
     */
    public function begin(): bool;

    /**
     * This function commits the open transaction.
     *
     * @return bool
     */
    public function commit(): bool;

    /**
     * This function rolls the open transaction back.
     *
     * @return bool
     */
    public function rollback(): bool;

    /**
     * This function runs a read and returns its result set.
     *
     * @param string $sql
     * @param array $placeholders Bound parameters
     * @return \Phalcon\Db\ResultInterface|bool
     */
    public function query(string $sql, array $placeholders = []);

    /**
     * This function runs a read and returns every row.
     *
     * @param string $sql
     * @param int $fetchMode A \PDO::FETCH_* mode
     * @param array $placeholders Bound parameters
     * @return array
     */
    public function fetchAll(string $sql, int $fetchMode = \PDO::FETCH_ASSOC, array $placeholders = []): array;

    /**
     * This function runs a read and returns its first row.
     *
     * @param string $sql
     * @param int $fetchMode A \PDO::FETCH_* mode
     * @param array $placeholders Bound parameters
     * @return array The row, or an empty array when there is none
     */
    public function fetchOne(string $sql, int $fetchMode = \PDO::FETCH_ASSOC, array $placeholders = []): array;

    /**
     * This function returns a table's columns.
     *
     * @param string $tableName
     * @return \Phalcon\Db\ColumnInterface[]
     */
    public function describeColumns(string $tableName): array;

    /**
     * This function returns a table's indexes.
     *
     * @param string $tableName
     * @return \Phalcon\Db\IndexInterface[]
     */
    public function describeIndexes(string $tableName): array;

    /**
     * This function tells whether a table exists.
     *
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool;

    /**
     * This function tells whether a view exists.
     *
     * @param string $viewName
     * @return bool
     */
    public function viewExists(string $viewName): bool;

    /**
     * This function quotes an identifier (a table, column or index name).
     *
     * @param string $identifier
     * @return string
     */
    public function escapeIdentifier(string $identifier): string;

    /**
     * This function quotes a string literal.
     *
     * @param string $value
     * @return string
     */
    public function escapeString(string $value): string;
}
