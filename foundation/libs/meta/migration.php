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
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation\Mvc\Model
 * @category Migration
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
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
     * @todo get table engines and collation from the model meta and system config
     * @param $model
     */
    public function up($model)
    {
        // Get the definitions of table/view from the meta data
        $meta = $this->getMetaData($model);

        // before migrating table or views, let's migrate functions. Because table or views use
        // functions. So migrating functions is required first.
        $this->migrateFunctions($model, $meta);

        //check whether meta is for table or View
        isset($meta[$model]['isView']) ? $this->migrateView($model, $meta) : $this->migrateTable($model, $meta);
    }

    /**
     * This function is responsible for migrating sql views according to the 
     * meta data of model.
     * 
     * @param $meta
     */
    private function migrateView($model, $meta)
    {
        $this::$connection->execute($this->di->get('dialect')->createView($meta[$model]['tableName'], $meta[$model]['viewSql']));
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
        $tableDefinition = $this->prepareTableDefinition($model, $meta);
        // Sync the table in the database
        $this->morphTable(
            $tableDefinition['tableName'],
            array(
                'columns' => $tableDefinition['columns'],
                'indexes' => $tableDefinition['indexes'],
                'options' => array(
                    'TABLE_TYPE' => 'BASE TABLE',
                    'AUTO_INCREMENT' => '',
                    'ENGINE' => 'InnoDB',
                    'TABLE_COLLATION' => 'utf8_unicode_ci'
                )
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
     * @param $meta 
     */
    private function migrateFunctions($model, $meta)
    {
        foreach ($meta[$model]['functions'] as $schema) {
            $functionExistsQuery = $this->di->get('dialect')->showFunction($schema['functionName']);
            $result = $this::$connection->query($functionExistsQuery)->fetch();

            //if function doesn't exists, then create.
            if ($result['Name'] == '') {
                $query = $this->di->get('dialect')->createFunction($schema['functionName'], $schema['parameters'], $schema['returnType'], $schema['statement']);
                $this::$connection->execute($query);
            }
        }
    }

    /**
     * This function is responsible for reading the individual metadata from
     * the application and process it for this class
     *
     * @todo Include parsing for all type of indexes
     * @param string $model
     * @return array
     */
    private function prepareTableDefinition($model, $meta)
    {
        // Initialize the array to be filled in
        $tableDescription = array(
            'tableName' => $meta[$model]['tableName'],
            'columns' => array(),
            'indexes' => array(),
        );

        // Traverse through the fields and process them
        foreach ($meta[$model]['fields'] as $field => $schema) {
            $fieldOptions = array();
            $fieldOptions['type'] = $this->di->get('metaManager')->getFieldType($schema['type']);
            if (isset($schema['length']))
                $fieldOptions['size'] = $schema['length'];
            $fieldOptions['notNull'] = !$schema['null'];
            if (isset($schema['autoIncrement'])) {
                $fieldOptions['autoIncrement'] = $schema['autoIncrement'];
            }

            if (isset($schema['default'])) {
                $fieldOptions['default'] = $schema['default'];
            }

            $tableDescription['columns'][] = new Column($schema['name'], $fieldOptions);
        }

        // Traverse through the indexes and process them
        foreach ($meta[$model]['indexes'] as $field => $type) {
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
     * @param $model
     * @return array
     */
    private function getMetaData($model)
    {
        return $this->di->get('fileHandler')->readFile(APP_PATH . metaManager::basePath . '/model/' . $model . '.php');
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
}
