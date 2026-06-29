<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Migration;

use Gaia\Db\Factory\AdapterConfig;
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
     * This function prepares the application database config for Phalcon migrations.
     *
     * @param array $config Merged application database configuration
     * @return array Configuration accepted by Phalcon migration setup
     */
    public function prepareMigrationConfig(array $config)
    {
        $config = AdapterConfig::withoutConnectionMeta($config);
        $config['adapter'] = 'mysql';

        return $config;
    }

    /**
     * This function returns table options passed to Phalcon morphTable.
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
     * instead of using morphTable.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @return bool
     */
    public function shouldSyncExistingTableOnly($connection, $tableName)
    {
        return false;
    }

    /**
     * This function syncs schema changes on an existing table without morphTable.
     *
     * @param \Phalcon\Db\AdapterInterface $connection Active database connection
     * @param string $tableName Table name from model metadata
     * @param Column[] $columns Expected column definitions from metadata
     */
    public function syncExistingTable($connection, $tableName, array $columns)
    {
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
