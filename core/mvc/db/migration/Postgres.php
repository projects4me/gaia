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
        $existingNames = array();
        $expectedNames = array();

        foreach ($existingColumns as $column) {
            $existingNames[] = $column->getName();
        }

        foreach ($columns as $column) {
            $expectedNames[] = $column->getName();
            if (!in_array($column->getName(), $existingNames, true)) {
                $connection->addColumn(
                    $tableName,
                    $this->getDefaultSchema(),
                    $this->prepareColumnForAdd($column)
                );
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
        foreach ($connection->describeIndexes($tableName) as $index) {
            foreach ($index->getColumns() as $columnName) {
                $existing[$columnName] = true;
            }
        }

        $quotedTable = $connection->escapeIdentifier($tableName);
        foreach ($indexes as $field => $type) {
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
            $fieldOptions['default'] = $schema['default'] ? 'true' : 'false';
        }

        return $fieldOptions;
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
                $definition['default'] = $default ? 'true' : 'false';
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
