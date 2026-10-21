<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Migration;

use Gaia\Db\Factory\AdapterConfig;
use Phalcon\Db\Column;

/**
 * PostgreSQL-specific schema migration behavior.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Mvc\Db\Migration
 * @category Postgres
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Postgres
{
    /**
     * This function prepares the application database config for Phalcon migrations.
     *
     * @param array $config Merged application database configuration
     * @return array Configuration accepted by Phalcon migration setup
     */
    public function prepareMigrationConfig(array $config)
    {
        $config = AdapterConfig::withoutConnectionMeta($config);
        $config['adapter'] = 'postgresql';
        unset($config['charset']);

        return $config;
    }

    /**
     * This function returns table options passed to Phalcon morphTable.
     *
     * @return array
     */
    public function getTableOptions()
    {
        return array();
    }

    /**
     * This function determines whether an existing table should be synced incrementally
     * instead of using morphTable.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @return bool
     */
    public function shouldSyncExistingTableOnly($connection, $tableName)
    {
        return $connection->tableExists($tableName);
    }

    /**
     * This function syncs existing tables without morphTable:
     * - adds missing columns
     * - drops columns that are no longer present in metadata
     * - changes the length of varchar columns whose metadata length differs
     * - adds missing non-primary indexes from metadata
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @param Column[] $columns Expected column definitions from metadata
     * @param array $indexes Optional metadata indexes map (field => type)
     */
    public function syncExistingTable($connection, $tableName, array $columns, array $indexes = [])
    {
        $existingColumns = $connection->describeColumns($tableName);
        $existingByName = array();
        $existingNames = array();
        $expectedNames = array();

        foreach ($existingColumns as $column) {
            $existingNames[] = $column->getName();
            $existingByName[$column->getName()] = $column;
        }

        foreach ($columns as $column) {
            $expectedNames[] = $column->getName();
            if (!in_array($column->getName(), $existingNames, true)) {
                $connection->addColumn(
                    $tableName,
                    $this->getDefaultSchema(),
                    $this->prepareColumnForAdd($column)
                );
            } elseif ($this->isVarcharLengthChange($existingByName[$column->getName()], $column)) {
                $this->alterVarcharLength($connection, $tableName, $column);
            }
        }

        foreach ($existingNames as $existingName) {
            if (!in_array($existingName, $expectedNames, true)) {
                // CASCADE clears dependent views/indexes that still reference legacy columns.
                $quotedTable = $connection->escapeIdentifier($tableName);
                $quotedColumn = $connection->escapeIdentifier($existingName);
                $connection->execute(
                    "ALTER TABLE {$quotedTable} DROP COLUMN IF EXISTS {$quotedColumn} CASCADE"
                );
            }
        }

        $this->syncIndexes($connection, $tableName, $indexes);
    }

    /**
     * This function determines whether an existing varchar column needs a new length.
     * Only varchar lengths are compared: other type differences between metadata and
     * PostgreSQL (e.g. bool stored as smallint) are expected and left alone.
     *
     * @param Column $existing Column as described by the database
     * @param Column $expected Column as defined in metadata
     * @return bool
     */
    private function isVarcharLengthChange(Column $existing, Column $expected)
    {
        return $existing->getType() === Column::TYPE_VARCHAR
            && $expected->getType() === Column::TYPE_VARCHAR
            && (int) $expected->getSize() > 0
            && (int) $existing->getSize() !== (int) $expected->getSize();
    }

    /**
     * This function changes a varchar column's length. PostgreSQL refuses to alter a column
     * that a view reads, so dependent views are dropped first; the migration driver
     * recreates every view after the tables. Shrinking stops with an error, before anything
     * is dropped, when existing values are longer than the new length; values are never
     * truncated. The drop and the alter run in one transaction.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @param Column $column Column as defined in metadata
     * @return void
     * @throws \Gaia\Exception\MigrationDriver
     */
    private function alterVarcharLength($connection, $tableName, Column $column)
    {
        $quotedTable = $connection->escapeIdentifier($tableName);
        $quotedColumn = $connection->escapeIdentifier($column->getName());
        $size = (int) $column->getSize();

        $tooLong = $connection->fetchOne(
            "SELECT COUNT(*) AS total FROM {$quotedTable} WHERE char_length({$quotedColumn}) > ?",
            \PDO::FETCH_ASSOC,
            array($size)
        );
        if ((int) $tooLong['total'] > 0) {
            throw new \Gaia\Exception\MigrationDriver(
                "Cannot shorten {$tableName}.{$column->getName()} to {$size} characters: "
                . "{$tooLong['total']} row(s) hold longer values."
            );
        }

        $connection->begin();
        try {
            $this->dropDependentViews($connection, $tableName, $column->getName());
            $connection->execute(
                "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} TYPE varchar({$size})"
            );
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
            throw $e;
        }
    }

    /**
     * This function drops the views that read a column. Without CASCADE, so a view built on
     * one of them (which the migration driver may not recreate) makes the drop fail instead.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table that owns the column
     * @param string $columnName Column the views read
     * @return void
     */
    private function dropDependentViews($connection, $tableName, $columnName)
    {
        $views = $connection->fetchAll(
            "SELECT DISTINCT view_class.relname AS name
             FROM pg_depend dependency
             JOIN pg_rewrite rewrite ON rewrite.oid = dependency.objid
             JOIN pg_class view_class ON view_class.oid = rewrite.ev_class
             JOIN pg_class table_class ON table_class.oid = dependency.refobjid
             JOIN pg_namespace table_schema ON table_schema.oid = table_class.relnamespace
             JOIN pg_attribute attribute ON attribute.attrelid = table_class.oid
                 AND attribute.attnum = dependency.refobjsubid
             WHERE table_schema.nspname = ?
             AND table_class.relname = ?
             AND attribute.attname = ?
             AND view_class.oid <> table_class.oid
             AND view_class.relkind = 'v'",
            \PDO::FETCH_ASSOC,
            array($this->getDefaultSchema(), $tableName, $columnName)
        );

        foreach ($views as $view) {
            $quotedView = $connection->escapeIdentifier($view['name']);
            $connection->execute("DROP VIEW IF EXISTS {$quotedView}");
        }
    }

    /**
     * Ensure non-primary indexes from metadata exist.
     *
     * @param \Phalcon\Db\AdapterInterface $connection
     * @param string $tableName
     * @param array $indexes
     * @return void
     */
    private function syncIndexes($connection, $tableName, array $indexes)
    {
        if (empty($indexes)) {
            return;
        }

        $existing = [];
        $existingNames = [];
        foreach ($connection->describeIndexes($tableName) as $index) {
            $existingNames[$index->getName()] = true;
            foreach ($index->getColumns() as $columnName) {
                $existing[$columnName] = true;
            }
        }

        $quotedTable = $connection->escapeIdentifier($tableName);
        foreach ($indexes as $field => $type) {
            if (is_array($type)) {
                $this->syncMultiColumnIndex($connection, $tableName, $field, $type, $existingNames);
                continue;
            }
            if ($type === 'primary' || isset($existing[$field])) {
                continue;
            }

            $indexName = $connection->escapeIdentifier($tableName . '_' . $field);
            $quotedField = $connection->escapeIdentifier($field);
            $unique = ($type === 'unique') ? 'UNIQUE ' : '';
            $connection->execute(
                "CREATE {$unique}INDEX IF NOT EXISTS {$indexName} ON {$quotedTable} ({$quotedField})"
            );
        }
    }

    /**
     * Ensure a multi-column index from metadata exists, matched by its name
     * (`{table}_{key}`) because its columns may also appear in other indexes.
     *
     * @param \Phalcon\Db\AdapterInterface $connection
     * @param string $tableName
     * @param string $key Index key in metadata
     * @param array  $definition ['type' => 'unique'|'index', 'columns' => [...]]
     * @param array  $existingNames Index names already on the table (name => true)
     * @return void
     */
    private function syncMultiColumnIndex($connection, $tableName, $key, array $definition, array $existingNames)
    {
        $name = $tableName . '_' . $key;
        if (isset($existingNames[$name]) || empty($definition['columns'])) {
            return;
        }

        $quotedColumns = [];
        foreach ($definition['columns'] as $column) {
            $quotedColumns[] = $connection->escapeIdentifier($column);
        }
        $unique = (isset($definition['type']) && $definition['type'] === 'unique') ? 'UNIQUE ' : '';
        $connection->execute(
            "CREATE {$unique}INDEX IF NOT EXISTS " . $connection->escapeIdentifier($name)
            . ' ON ' . $connection->escapeIdentifier($tableName)
            . ' (' . implode(', ', $quotedColumns) . ')'
        );
    }

    /**
     * This function maps MySQL-oriented metadata field types to PostgreSQL Phalcon types.
     *
     * @param string $metadataType Field type defined in model metadata
     * @param int    $phalconType Initial Phalcon column type from meta manager
     * @return int Normalized Phalcon column type
     */
    public function normalizeColumnType($metadataType, $phalconType)
    {
        $type = strtolower($metadataType);

        if (in_array($type, array('tinytext', 'mediumtext', 'longtext'), true)) {
            return Column::TYPE_TEXT;
        }
        if (in_array($type, array('tinyblob', 'mediumblob', 'longblob'), true)) {
            return Column::TYPE_BYTEA;
        }
        if ($type === 'blob') {
            return Column::TYPE_BYTEA;
        }
        if ($type === 'datetime') {
            return Column::TYPE_TIMESTAMP;
        }

        return $phalconType;
    }

    /**
     * This function applies adapter-specific column options before migration.
     *
     * @param array  $schema Field schema from model metadata
     * @param array  $fieldOptions Base Phalcon column options
     * @param array  $modelIndexes Index definitions from model metadata
     * @param string $field Field key from model metadata
     * @return array Final Phalcon column options
     */
    public function prepareColumnOptions(array $schema, array $fieldOptions, array $modelIndexes, $field)
    {
        if (isset($modelIndexes[$field]) && $modelIndexes[$field] === 'primary') {
            $fieldOptions['primary'] = true;
        }

        if (isset($schema['default']) && $fieldOptions['type'] === Column::TYPE_BOOLEAN) {
            $fieldOptions['default'] = self::booleanDefaultLiteral($schema['default']);
        }

        return $fieldOptions;
    }

    /**
     * Normalize a metadata / Phalcon default into a Postgres boolean literal.
     *
     * PHP treats the non-empty string "false" as truthy, so string defaults from
     * an earlier pass must not use bare `(bool)` / ternary coercion.
     *
     * @param mixed $value
     * @return string
     */
    private static function booleanDefaultLiteral($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return ((int) $value) > 0 ? 'true' : 'false';
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, array('1', 'true', 't', 'yes', 'y', 'on'), true)) {
                return 'true';
            }
            if (in_array($normalized, array('0', 'false', 'f', 'no', 'n', 'off', ''), true)) {
                return 'false';
            }
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    /**
     * This function determines whether a separate PRIMARY index should be emitted.
     *
     * @return bool
     */
    public function shouldEmitPrimaryIndex()
    {
        return false;
    }

    /**
     * This function determines whether column charset/collation migration is supported.
     *
     * @return bool
     */
    public function supportsCollationMigration()
    {
        return false;
    }

    /**
     * This function returns the default database schema for addColumn operations.
     *
     * @return string
     */
    public function getDefaultSchema()
    {
        return 'public';
    }

    /**
     * This function prepares a column definition before it is added to a populated table.
     * New columns are added nullable first to avoid failures on existing rows.
     *
     * @param Column $column Column definition built from metadata
     * @return Column Column definition ready for addColumn
     */
    public function prepareColumnForAdd(Column $column)
    {
        $definition = array(
            'type' => $column->getType(),
            'notNull' => false,
        );

        if ($column->getSize()) {
            $definition['size'] = $column->getSize();
        }
        if ($column->hasDefault()) {
            $default = $column->getDefault();
            if ($column->getType() === Column::TYPE_BOOLEAN) {
                $definition['default'] = self::booleanDefaultLiteral($default);
            } else {
                $definition['default'] = $default;
            }
        }
        if ($column->isAutoIncrement()) {
            $definition['type'] = Column::TYPE_INTEGER;
            $definition['notNull'] = false;
        }

        return new Column($column->getName(), $definition);
    }
}
