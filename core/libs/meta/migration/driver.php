<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta\Migration;

use Gaia\Libraries\Meta\Manager as metaManager;
use Gaia\Db\Factory\ConnectionFactory;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Db\Migration\AdapterConnection;
use Gaia\Db\Migration\SchemaConnection;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Exception as DbException;
use Phalcon\Mvc\Model\Exception as ModelException;
use PDOException;

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
     * @var \Phalcon\Di\DiInterface $di
     */
    protected $di;

    /**
     * Driver constructor.
     *
     * @param \Phalcon\Di\DiInterface $di
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }


    /**
     * This function traverses model metadata directory and migrates every model
     * defined: it creates or syncs their tables, functions, triggers and views.
     *
     * @todo   Allow migration for a single model
     * @return array Success or Error
     */
    public function migrate()
    {
        return $this->run(new AdapterConnection($this->openConnection()));
    }

    /**
     * This function works out what migrate() would run, without running it: the
     * migration runs on a PlanningConnection, which reads from the database and adds
     * every write to the returned plan, each with its table.
     *
     * @return Plan
     */
    public function plan()
    {
        $plan = new Plan();
        $this->run(new PlanningConnection($this->openConnection(), $plan), $plan);
        return $plan;
    }

    /**
     * This function returns a connection built from the global database settings,
     * the one the migration runs on.
     *
     * @return AdapterInterface
     */
    private function openConnection()
    {
        return ConnectionFactory::create($GLOBALS['settings']->database->toArray());
    }

    /**
     * This function migrates every model on the given connection. When a plan is
     * given (a dry run), it names the table on the plan before each step, so the
     * statements the planning connection adds are attributed to it; a real migrate
     * passes none and does no plan work.
     *
     * @param SchemaConnection $connection
     * @param Plan|null $plan
     * @return array Success or Error
     */
    private function run(SchemaConnection $connection, ?Plan $plan = null)
    {
        $result = array();
        $models = array();

        try {
            // Set the metadata directory
            $migrationsDir = APP_PATH . metaManager::basePath . '/model';

            $migration = $this->di->get('metaMigration');
            $migration->setConnection($connection);

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
            if ($plan !== null) {
                $plan->forTable('(extensions)');
            }
            foreach ($migration->migrateExtensions($extensions) as $skipped) {
                $message = "Extension $skipped could not be created (not available on this server, or the database user may not create it); skipped";
                $result['extension:' . $skipped] = $message;
                error_log("Migration: PostgreSQL $message");
            }

            // before migrating table or views, let's migrate functions. Because table or views use
            // functions. So migrating functions is required first.
            foreach ($models as $model) {
                $this->planFor($plan, $connection, $model);
                $migration->migrateFunctions($model);
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
                $this->planFor($plan, $connection, $model);
                $migration->migrateModel($model);
                $migration->migrateColumnCollation($model);
                $result[$model] = "$model migrated successfully";
            }

            foreach ($views as $model) {
                $result[$model] = "Failed to migrate $model";
                $this->planFor($plan, $connection, $model, true);
                $migration->migrateModel($model);
                $result[$model] = "$model migrated successfully";
            }
        } catch (DbException | ModelException | PDOException $e) {
            throw new \Gaia\Exception\MigrationDriver($e->getMessage());
        }
        return $result;
    }

    /**
     * This function names a model's table on the plan, before the step that migrates
     * it. Every pass drops and recreates the views, so rebuilding a view that exists
     * is routine, not a change. Without a plan (a real migrate) it does nothing.
     *
     * @param Plan|null $plan
     * @param SchemaConnection $connection
     * @param string $model
     * @param bool $isView
     * @return void
     */
    private function planFor($plan, SchemaConnection $connection, string $model, bool $isView = false)
    {
        if ($plan === null) {
            return;
        }
        $meta = $this->di->get('fileHandler')->readFile(
            APP_PATH . metaManager::basePath . '/model/' . $model . '.php'
        );
        $table = isset($meta[$model]['tableName']) ? $meta[$model]['tableName'] : $model;
        $plan->forTable($table, $isView && $connection->viewExists($table));
    }
}
