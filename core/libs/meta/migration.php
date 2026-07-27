<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta;

/**
 * In the project we do not need to use the phalcon-dev anywhere other than this file
 * therefore we are not including the phalcon-devtool in the application bootstrap using
 * dependency injector to help avoid extra load
 */
// require_once APP_PATH.'/vendor/phalcon/phalcon-devtools/scripts/Phalcon/Mvc/Model/Migration.php';
// require_once APP_PATH.'/vendor/phalcon/phalcon-devtools/scripts/Phalcon/Migrations.php';

use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Migrations\Mvc\Model\Migration as PhalconMigration;
use Gaia\Libraries\Meta\Manager as metaManager;

/**
 * This class is responsible for synchronizing the database by using the
 * metadata defined for all the models in app\metadata\model.
 *
 * This calss is dependant on Phalcon Dev Tools.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation\Mvc\Model
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Migration extends PhalconMigration
{

    /**
     * The dependency injector used by this class
     *
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    /**
     * Migration constructor.
     *
     * @param \Phalcon\DiInterface $di
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * This function is responsible for synchronizing the database using the
     * metadata. As per the Phalcon Dev Tools this function is to be implemented
     * in the individual files in app\migrations. However, since in this
     * application we are relying on model metadata defined through files in
     * app\metadata\model it would be redundant to have the columns defined
     * again so this function get the metadata from the files and sets them up
     *
     * @todo  get table engines and collation from the model meta and system config
     * @param $model
     */
    public function up($model)
    {
        // Get the definitions of table/view from the meta data
        $meta = $this->getMetaData($model);

        if (isset($meta[$model]['isView'])) {
            $this->migrateView($model, $meta);
            return;
        }

        $this->migrateTable($model, $meta);
    }

    /**
     * This function is responsible for migrating sql views according to the 
     * meta data of model.
     * 
     * @param $meta
     */
    private function migrateView($model, $meta)
    {
        $tableName = $meta[$model]['tableName'];

        // Column shape changes need a full recreate; CREATE OR REPLACE cannot rename/drop view columns.
        $this::$connection->execute('DROP VIEW IF EXISTS "' . $tableName . '" CASCADE');
        $this::$connection->execute($this->di->get('dialect')->createView($tableName, $meta[$model]['viewSql']));
    }

    /**
     * This function is responsible for migrating sql tables according to the 
     * meta data of model. It first call migrateTrigger function in order to migrate
     * the triggers associated with that table. After that it call prepareTableDefinition
     * function in order to get Table definition that Phalcon's morphTable want to migrate
     * a table.
     * 
     * @param $model
     * @param $meta 
     */
    private function migrateTable($model, $meta)
    {
        $handler = $this->di->get('migrationHandler');
        $tableDefinition = $this->prepareTableDefinition($model, $meta);
        $tableName = $tableDefinition['tableName'];

        if ($handler->shouldSyncExistingTableOnly(self::$connection, $tableName)) {
            $handler->syncExistingTable(
                self::$connection,
                $tableName,
                $tableDefinition['columns'],
                isset($meta[$model]['indexes']) ? $meta[$model]['indexes'] : []
            );
            $this->migrateTriggers($model, $meta);
            return;
        }

        $this->morphTable(
            $tableName,
            array(
                'columns' => $tableDefinition['columns'],
                'indexes' => $tableDefinition['indexes'],
                'options' => $handler->getTableOptions(),
            )
        );

        $this->migrateTriggers($model, $meta);
    }

    /**
     * This function is responsible for migrating triggers, attached to a specific
     * table. It checks whether trigger exist or not, if it not exists than it will
     * create a new one.
     *
     * @param $model
     * @param $meta
     */
    private function migrateTriggers($model, $meta)
    {
        if (empty($meta[$model]['triggers'])) {
            return;
        }

        foreach ($meta[$model]['triggers'] as $schema) {
            $triggerExistsQuery = $this->di->get('dialect')->showTrigger($meta[$model]['tableName']);
            $triggers = $this::$connection->query($triggerExistsQuery)->fetchAll();
            $triggerExists = false;

            foreach($triggers as $trigger) {
                if((in_array($schema['triggerName'], $trigger))) {
                    $triggerExists = true;
                }
            }

            //if trigger doesn't exists, then create.
            if (!$triggerExists) {
                $query = $this->di->get('dialect')->createTrigger($meta[$model]['tableName'], $schema);
                $this::$connection->execute($query);
            }
        }
    }

    /**
     * This function is responsible for migrating functions that will be used by 
     * tables or views.
     * 
     * @param $model
     */
    public function migrateFunctions($model)
    {
        $meta = $this->getMetaData($model);

        if (empty($meta[$model]['functions'])) {
            return;
        }

        foreach ($meta[$model]['functions'] as $schema) {
            $functionExistsQuery = $this->di->get('dialect')->showFunction($schema['functionName']);
            $result = $this::$connection->query($functionExistsQuery)->fetch();

            //if function doesn't exists, then create.
            if (empty($result) || empty($result['Name'])) {
                $query = $this->di->get('dialect')->createFunction($schema['functionName'], $schema['parameters'], $schema['returnType'], $schema['statement']);
                $this::$connection->execute($query);
            }
        }
    }

    /**
     * This function is responsible for reading the individual metadata from
     * the application and process it for this class
     *
     * @todo   Include parsing for all type of indexes
     * @param  string $model
     * @return array
     */
    private function prepareTableDefinition($model, $meta)
    {
        $handler = $this->di->get('migrationHandler');
        $metaManager = $this->di->get('metaManager');

        // Initialize the array to be filled in
        $tableDescription = array(
            'tableName' => $meta[$model]['tableName'],
            'columns' => array(),
            'indexes' => array(),
        );

        // Traverse through the fields and process them
        foreach ($meta[$model]['fields'] as $field => $schema) {
            $fieldOptions = array();
            $fieldOptions['type'] = $handler->normalizeColumnType(
                $schema['type'],
                $metaManager->getFieldType($schema['type'])
            );
            if (isset($schema['length'])) {
                $fieldOptions['size'] = $schema['length'];
            }
            $fieldOptions['notNull'] = !$schema['null'];
            if (isset($schema['autoIncrement'])) {
                $fieldOptions['autoIncrement'] = $schema['autoIncrement'];
            }

            if (isset($schema['default'])) {
                $fieldOptions['default'] = $schema['default'];
            }

            $fieldOptions = $handler->prepareColumnOptions(
                $schema,
                $fieldOptions,
                $meta[$model]['indexes'],
                $field
            );

            // Add charset and collation if both are present in metadata
            if ($this->shouldApplyCollation($schema['type'])
                && isset($schema['charset'])
                && isset($schema['collation'])
            ) {
                $fieldOptions['charset'] = $schema['charset'];
                $fieldOptions['collation'] = $schema['collation'];
            }

            $tableDescription['columns'][] = new Column($schema['name'], $fieldOptions);
        }

        // Traverse through the indexes and process them
        foreach ($meta[$model]['indexes'] as $field => $type) {
            if ($type === 'primary' && !$handler->shouldEmitPrimaryIndex()) {
                continue;
            }
            // need to be able to recognize all types of indexes
            $indexType = '';
            $name = '';
            if ($type == 'primary') {
                $name = $indexType = 'PRIMARY';
                $tableDescription['indexes'][] = new Index($name, array($field), $indexType);
            } else if ($type == 'unique') {
                $indexType = 'UNIQUE';
                $name = $meta[$model]['tableName'] . '_' . $field;
                $tableDescription['indexes'][] = new Index($name, array($field), $indexType);
            } else {
                $indexType = 'INDEX';
                $name = $meta[$model]['tableName'] . '_' . $field;
                $tableDescription['indexes'][] = new Index($name, array($field));
            }
        }

        return $tableDescription;
    }

    /**
     * This function returns metadata of given model.
     *
     * @param  $model
     * @return array
     */
    private function getMetaData($model)
    {
        return $this->di->get('fileHandler')->readFile(APP_PATH . metaManager::basePath . '/model/' . $model . '.php');
    }

    /**
     * This function determines if a field type should support charset/collation.
     * Only text-based column types support charset/collation.
     *
     * @param  string $fieldType
     * @return bool
     */
    private function shouldApplyCollation($fieldType)
    {
        $textTypes = array('varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext');
        return in_array(strtolower($fieldType), $textTypes);
    }

    /**
     * This function initiate a new instance on Migration class and use it to
     * perform the migration
     *
     * @param string $model model name to be migrated
     */
    public function migrateModel($model)
    {
        $migration = new Migration($this->di);
        $migration->up($model);
    }

    /**
     * This function is responsible for migrating column charset and collation
     * for existing tables. It compares the metadata with the current database
     * state and updates columns that have charset/collation defined in metadata.
     *
     * @param string $model
     */
    public function migrateColumnCollation($model)
    {
        $handler = $this->di->get('migrationHandler');

        if (!$handler->supportsCollationMigration()) {
            return;
        }

        $dialect = $this->di->get('dialect');
        $meta = $this->getMetaData($model);
        
        // Skip if this is a view
        if (isset($meta[$model]['isView'])) {
            return;
        }

        $tableName = $meta[$model]['tableName'];
        $metaManager = $this->di->get('metaManager');

        // Process each field that has charset and collation defined
        foreach ($meta[$model]['fields'] as $field => $schema) {
            if (!isset($schema['charset']) || !isset($schema['collation'])) {
                continue;
            }

            if (!$this->shouldApplyCollation($schema['type'])) {
                continue;
            }

            $columnName = $schema['name'];
            $expectedCharset = $schema['charset'];
            $expectedCollation = $schema['collation'];

            $collationQuery = $dialect->getColumnCollation($tableName, $columnName);
            $result = $this::$connection->query($collationQuery)->fetch();

            if (empty($result) || empty($result['COLLATION_NAME'])) {
                continue;
            }

            $currentCollation = $result['COLLATION_NAME'];
            $currentCharset = $result['CHARACTER_SET_NAME'];

            if ($currentCollation !== $expectedCollation
                || $currentCharset !== $expectedCharset
            ) {
                $columnType = $this->getColumnTypeString($schema, $metaManager);
                $columnDefinition = $columnType;
                
                if (isset($schema['length'])) {
                    $columnDefinition .= '(' . $schema['length'] . ')';
                }

                $alterQuery = $dialect->alterColumnCollation(
                    $tableName,
                    $columnName,
                    $columnDefinition,
                    $expectedCharset,
                    $expectedCollation
                );

                $this::$connection->execute($alterQuery);
            }
        }
    }

    /**
     * This function converts metadata field type to SQL column type string.
     *
     * @param  array                        $schema
     * @param  \Gaia\Libraries\Meta\Manager $metaManager
     * @return string
     */
    private function getColumnTypeString($schema, $metaManager)
    {
        $type = strtolower($schema['type']);
        
        $typeMap = array(
            'varchar' => 'VARCHAR',
            'char' => 'CHAR',
            'text' => 'TEXT',
            'tinytext' => 'TINYTEXT',
            'mediumtext' => 'MEDIUMTEXT',
            'longtext' => 'LONGTEXT',
            'int' => 'INT',
            'bigint' => 'BIGINT',
            'float' => 'FLOAT',
            'decimal' => 'DECIMAL',
            'datetime' => 'DATETIME',
            'date' => 'DATE',
            'bool' => 'TINYINT',
            'blob' => 'BLOB',
            'tinyblob' => 'TINYBLOB',
            'mediumblob' => 'MEDIUMBLOB',
            'longblob' => 'LONGBLOB'
        );

        return isset($typeMap[$type]) ? $typeMap[$type] : strtoupper($type);
    }
}
