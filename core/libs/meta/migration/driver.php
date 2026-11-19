<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta\Migration;

use Gaia\Libraries\Meta\Manager as metaManager;
use Gaia\Db\Factory\ConnectionFactory;
use Gaia\Libraries\Search\SearchableRegistry;

/**
 * This class is responsible for synchronizing the database by using the
 * metadata defined for all the models in app\metadata\model.
 *
 * Tables and views are created and synced by Gaia\Libraries\Meta\Migration.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation\Mvc\Model\Migration
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Driver
{
    /**
     * The dependency injector used by this class
     *
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    /**
     * Driver constructor.
     *
     * @param \Phalcon\DiInterface $di
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }


    /**
     * This function traverses model metadata directory and attempts to run
     * migration for all the models defined
     *
     * @todo   Allow migration for a single model
     * @return array Success or Error
     */
    public function migrate()
    {
        $result = array();
        $models = array();

        try {
            // Set the metadata directory
            $migrationsDir = APP_PATH . metaManager::basePath . '/model';

            // The migration runs on its own connection, built from the global database settings
            $this->di->get('metaMigration')->setConnection(
                ConnectionFactory::create($GLOBALS['settings']->database->toArray())
            );

            // iterate through all the model metadata defined in the system
            $iterator = new \DirectoryIterator($migrationsDir);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    if (preg_match('/\.php$/', $fileinfo->getFilename())) {
                        $models[] = str_replace('.php', '', basename($fileinfo->getFilename()));
                    }
                }
            }

            // Before any DDL, extensions included: every search block is valid and no two models share a search type.
            (new SearchableRegistry($this->di->get('metaManager'), $models))->types();

            // Extensions before any model DDL: vector and tsvector columns and their indexes need them.
            $database = $GLOBALS['settings']->database;
            $extensions = isset($database->extensions) ? $database->extensions->toArray() : array();
            foreach ($this->di->get('metaMigration')->migrateExtensions($extensions) as $skipped) {
                $message = "Extension $skipped could not be created (not available on this server, or the database user may not create it); skipped";
                $result['extension:' . $skipped] = $message;
                error_log("Migration: PostgreSQL $message");
            }

            // before migrating table or views, let's migrate functions. Because table or views use
            // functions. So migrating functions is required first.
            foreach ($models as $model) {
                $this->di->get('metaMigration')->migrateFunctions($model);
            }

            $views = [];
            $tables = [];
            foreach ($models as $model) {
                $meta = $this->di->get('fileHandler')->readFile(
                    APP_PATH . metaManager::basePath . '/model/' . $model . '.php'
                );
                if (isset($meta[$model]['isView']) && $meta[$model]['isView']) {
                    $views[] = $model;
                } else {
                    $tables[] = $model;
                }
            }

            // Tables first so new columns exist before views are rebuilt, and obsolete
            // columns can be dropped (CASCADE may remove dependent views temporarily).
            foreach ($tables as $model) {
                $result[$model] = "Failed to migrate $model";
                $this->di->get('metaMigration')->migrateModel($model);
                $this->di->get('metaMigration')->migrateColumnCollation($model);
                $result[$model] = "$model migrated successfully";
            }

            foreach ($views as $model) {
                $result[$model] = "Failed to migrate $model";
                $this->di->get('metaMigration')->migrateModel($model);
                $result[$model] = "$model migrated successfully";
            }
        } catch (PhalconException $e) {
            throw new \Gaia\Exception\MigrationDriver($e->getMessage());
        }
        return $result;
    }
}
