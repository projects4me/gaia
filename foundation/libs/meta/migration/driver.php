<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta\Migration;

use Gaia\Libraries\Meta\Manager as metaManager;
use Gaia\Libraries\Meta\Migration as foundationMigration;

/**
 * This class is responsible for synchronizing the database by using the
 * metadata defined for all the models in app\metadata\model.
 * 
 * This calss is dependant on Phalcon Dev Tools. 
 *  
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation\Mvc\Model\Migration
 * @category Migration
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Driver
{
    /**
     * This function traverses model metadata directory and attempts to run
     * migration for all the models defined
     * 
     * @todo Allow migration for a single model
     * @return array Sucess or Error
     */
    public static function migrate()
    {
        $result = array();
        try {
            // Set the metadata directory
            $migrationsDir = APP_PATH.metaManager::basePath.'/model';
            // setup the database using global settings
            foundationMigration::setup($GLOBALS['settings']->database);

            // iterate through all the model metadata defined in the system
            $iterator = new \DirectoryIterator($migrationsDir);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    if (preg_match('/\.php$/', $fileinfo->getFilename())) {
                        $model = str_replace('.php', '', basename($fileinfo->getFilename()));
                        $result[$model] = 'Failed to migrate '.$model;
                        foundationMigration::migrateModel($model);
                        $result[$model] = $model." migrated successfully";
                    }
                }
            }
       } catch (PhalconException $e) {
           print($e->getMessage());
       } catch (Exception $e) {
           if ($extensionLoaded) {
               print Color::error($e->getMessage()) . PHP_EOL;
           } else {
               print 'ERROR: ' . $e->getMessage() . PHP_EOL;
           }
       }
       return $result;
    }
}