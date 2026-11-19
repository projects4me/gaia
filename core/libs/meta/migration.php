<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta;

use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Adapter\AdapterInterface;
use Gaia\Libraries\Meta\Manager as metaManager;
use Gaia\Libraries\Search\SearchBlock;

/**
 * This class is responsible for synchronizing the database by using the
 * metadata defined for all the models in app\metadata\model.
 *
 * New tables are created with the database adapter's createTable(); existing tables
 * are synced by the adapter's migration handler (migrationHandler).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation\Mvc\Model
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Migration
{

    /**
     * The dependency injector used by this class
     *
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    /**
     * The database connection the migration runs on, set by setConnection()
     *
     * @var AdapterInterface $connection
     */
    private $connection;

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
     * This function sets the database connection the migration runs on.
     *
     * @param AdapterInterface $connection
     * @return void
     */
    public function setConnection(AdapterInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * This function returns the database connection the migration runs on.
     *
     * @return AdapterInterface
     * @throws \LogicException When setConnection() has not been called
     */
    private function getConnection()
    {
        if ($this->connection === null) {
            throw new \LogicException('Migration has no database connection: call setConnection() first');
        }
        return $this->connection;
    }

    /**
     * This function is responsible for synchronizing the database using the
     * metadata defined through files in app\metadata\model: it reads the
     * metadata of the model and creates or syncs its table, or rebuilds its view
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
        $this->getConnection()->execute('DROP VIEW IF EXISTS "' . $tableName . '" CASCADE');
        $this->getConnection()->execute($this->di->get('dialect')->createView($tableName, $meta[$model]['viewSql']));
    }

    /**
     * This function is responsible for migrating sql tables according to the 
     * meta data of model. It first call migrateTrigger function in order to migrate
     * the triggers associated with that table. After that it call prepareTableDefinition
     * function in order to get the table definition. A missing table is created with the
     * adapter's createTable(); an existing one is synced by the migration handler.
     * 
     * @param $model
     * @param $meta 
     */
    private function migrateTable($model, $meta)
    {
        $handler = $this->di->get('migrationHandler');
        $tableDefinition = $this->prepareTableDefinition($model, $meta);
        $tableName = $tableDefinition['tableName'];

        $indexes = isset($meta[$model]['indexes']) ? $meta[$model]['indexes'] : [];
        // Before any DDL: an unsupported index kind or a missing extension leaves the table untouched.
        $handler->checkSearchSchema($this->getConnection(), $tableName, $tableDefinition['searchColumns'], $indexes);

        if ($handler->shouldSyncExistingTableOnly($this->getConnection(), $tableName)) {
            $handler->syncExistingTable(
                $this->getConnection(),
                $tableName,
                $tableDefinition['columns'],
                $indexes,
                $tableDefinition['searchColumns']
            );
            $this->migrateTriggers($model, $meta);
            return;
        }

        $this->getConnection()->createTable(
            $tableName,
            (string) $handler->getDefaultSchema(),
            array(
                'columns' => $tableDefinition['columns'],
                'indexes' => $tableDefinition['indexes'],
                'options' => $handler->getTableOptions(),
            )
        );
        $handler->syncSearchSchema($this->getConnection(), $tableName, $tableDefinition['searchColumns'], $indexes);

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
            $triggers = $this->getConnection()->query($triggerExistsQuery)->fetchAll();
            $triggerExists = false;

            foreach($triggers as $trigger) {
                if((in_array($schema['triggerName'], $trigger))) {
                    $triggerExists = true;
                }
            }

            //if trigger doesn't exists, then create.
            if (!$triggerExists) {
                $query = $this->di->get('dialect')->createTrigger($meta[$model]['tableName'], $schema);
                $this->getConnection()->execute($query);
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
            $result = $this->getConnection()->query($functionExistsQuery)->fetch();

            //if function doesn't exists, then create.
            if (empty($result) || empty($result['Name'])) {
                $query = $this->di->get('dialect')->createFunction($schema['functionName'], $schema['parameters'], $schema['returnType'], $schema['statement']);
                $this->getConnection()->execute($query);
            }
        }
    }

    /**
     * This function creates the database extensions listed in the database config
     * (`extensions`), e.g. vector and pg_trgm. It is idempotent.
     *
     * @param array $extensions Extension names
     * @return array Names of the extensions that were not created: the server does not
     *               ship them or the database user may not create them
     */
    public function migrateExtensions(array $extensions)
    {
        return $this->di->get('migrationHandler')->createExtensions($this->getConnection(), $extensions);
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
            // vector/tsvector columns, which Phalcon's Column cannot express: name => definition
            'searchColumns' => array(),
        );

        // Traverse through the fields and process them
        foreach ($meta[$model]['fields'] as $field => $schema) {
            $searchDefinition = $handler->searchColumnDefinition($schema, $meta[$model]['fields']);
            if ($searchDefinition !== null) {
                $tableDescription['searchColumns'][$schema['name']] = $searchDefinition;
                continue;
            }

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
            // gin/gin_trgm/hnsw_cosine indexes are created by the handler's syncSearchSchema
            if ($handler->isSearchIndexKind($type)) {
                continue;
            }
            // Multi-column index: 'name' => ['type' => 'unique'|'index', 'columns' => [...]]
            if (is_array($type)) {
                // One on a vector/tsvector column waits for syncSearchSchema, which adds that column.
                if (array_intersect($type['columns'], array_keys($tableDescription['searchColumns']))) {
                    continue;
                }
                $name = $meta[$model]['tableName'] . '_' . $field;
                $indexType = (isset($type['type']) && $type['type'] === 'unique') ? 'UNIQUE' : '';
                $tableDescription['indexes'][] = new Index($name, $type['columns'], $indexType);
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
     * This function returns metadata of given model, with the columns and indexes of its search block.
     *
     * @param  $model
     * @return array
     */
    private function getMetaData($model)
    {
        $meta = $this->di->get('fileHandler')->readFile(APP_PATH . metaManager::basePath . '/model/' . $model . '.php');
        if (isset($meta[$model]) && is_array($meta[$model])) {
            $meta[$model] = SearchBlock::apply($model, $meta[$model]);
        }

        return $meta;
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
     * This function migrates the table or view of a model
     *
     * @param string $model model name to be migrated
     */
    public function migrateModel($model)
    {
        $this->up($model);
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
            $result = $this->getConnection()->query($collationQuery)->fetch();

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

                $this->getConnection()->execute($alterQuery);
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
