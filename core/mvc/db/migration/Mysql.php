<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Migration;

use Phalcon\Db\Column;

/**
 * MySQL-specific schema migration behavior.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Mvc\Db\Migration
 * @category Mysql
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Mysql
{
    /**
     * This function returns table options passed to the adapter's createTable().
     *
     * @return array
     */
    public function getTableOptions()
    {
        return array(
            'TABLE_TYPE' => 'BASE TABLE',
            'AUTO_INCREMENT' => '',
            'ENGINE' => 'InnoDB',
            'TABLE_COLLATION' => 'utf8_unicode_ci',
        );
    }

    /**
     * This function determines whether an existing table should be synced incrementally
     * instead of being created.
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
     * This function leaves an existing MySQL table as it is: since phalcon/migrations was
     * removed, migrate only creates missing MySQL tables and no longer alters existing ones.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @param Column[] $columns Expected column definitions from metadata
     * @param array $indexes Optional metadata indexes map (field => type)
     * @param array $searchColumns Unused: MySQL has no search columns
     */
    public function syncExistingTable($connection, $tableName, array $columns, array $indexes = [], array $searchColumns = [])
    {
    }

    /**
     * This function ignores the extension list: MySQL has no extensions to create.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param array $extensions Extension names from the database config
     * @return array Always empty
     */
    public function createExtensions($connection, array $extensions)
    {
        return array();
    }

    /**
     * This function rejects vector and tsvector fields, which only PostgreSQL supports.
     *
     * @param array $schema Field schema from model metadata
     * @param array $fields All field schemas of the model, keyed by field name
     * @return null
     * @throws \Gaia\Exception\MigrationDriver
     */
    public function searchColumnDefinition(array $schema, array $fields)
    {
        if (PostgresSearchDdl::isSearchType($schema['type'])) {
            throw new \Gaia\Exception\MigrationDriver(
                "Field {$schema['name']} of type {$schema['type']} needs PostgreSQL."
            );
        }

        return null;
    }

    /**
     * This function tells whether a metadata index type is a search index kind.
     *
     * @param mixed $type Index type from model metadata
     * @return bool
     */
    public function isSearchIndexKind($type)
    {
        return PostgresSearchDdl::isSearchIndexKind($type);
    }

    /**
     * This function rejects search indexes, which only PostgreSQL supports. It runs
     * before the table is created or changed.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @param array $searchColumns Column name => definition (always empty on MySQL)
     * @param array $indexes Metadata indexes map (field => type)
     * @return void
     * @throws \Gaia\Exception\MigrationDriver
     */
    public function checkSearchSchema($connection, $tableName, array $searchColumns, array $indexes)
    {
        foreach ($indexes as $field => $type) {
            if ($this->isSearchIndexKind($type)) {
                throw new \Gaia\Exception\MigrationDriver(
                    "Index {$tableName}.{$field} of kind {$type} needs PostgreSQL."
                );
            }
        }
    }

    /**
     * This function has nothing to sync on MySQL; it repeats the search index check.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @param array $searchColumns Column name => definition (always empty on MySQL)
     * @param array $indexes Metadata indexes map (field => type)
     * @return void
     * @throws \Gaia\Exception\MigrationDriver
     */
    public function syncSearchSchema($connection, $tableName, array $searchColumns, array $indexes)
    {
        $this->checkSearchSchema($connection, $tableName, $searchColumns, $indexes);
    }

    /**
     * This function maps metadata field types to Phalcon column types.
     *
     * @param string $metadataType Field type defined in model metadata
     * @param int    $phalconType Initial Phalcon column type from meta manager
     * @return int Normalized Phalcon column type
     */
    public function normalizeColumnType($metadataType, $phalconType)
    {
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
        return $fieldOptions;
    }

    /**
     * This function determines whether a separate PRIMARY index should be emitted.
     *
     * @return bool
     */
    public function shouldEmitPrimaryIndex()
    {
        return true;
    }

    /**
     * This function determines whether column charset/collation migration is supported.
     *
     * @return bool
     */
    public function supportsCollationMigration()
    {
        return true;
    }

    /**
     * This function returns the default database schema for addColumn operations.
     *
     * @return string|null
     */
    public function getDefaultSchema()
    {
        return null;
    }

    /**
     * This function prepares a column definition before it is added to an existing table.
     *
     * @param Column $column Column definition built from metadata
     * @return Column Column definition ready for addColumn
     */
    public function prepareColumnForAdd(Column $column)
    {
        return $column;
    }
}
