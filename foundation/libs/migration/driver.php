<?php

/* 
 * Projects4Me Community Edition is an open source project management software 
 * developed by PROJECTS4ME Inc. Copyright (C) 2015-2016 PROJECTS4ME Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 (GNU AGPL v3) as
 * published be the Free Software Foundation with the addition of the following 
 * permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF 
 * THE COVERED WORK IN WHICH THE COPYRIGHT IS OWNED BY PROJECTS4ME Inc., 
 * Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.
 * 
 * You should have received a copy of the GNU AGPL v3 along with this program; 
 * if not, see http://www.gnu.org/licenses or write to the Free Software 
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * You can contact PROJECTS4ME, Inc. at email address contact@projects4.me.
 * 
 * The interactive user interfaces in modified source and object code versions 
 * of this program must display Appropriate Legal Notices, as required under 
 * Section 5 of the GNU AGPL v3.
 * 
 * In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal 
 * Notices must retain the display of the "Powered by Projects4Me" logo. If the 
 * display of the logo is not reasonably feasible for technical reasons, the 
 * Appropriate Legal Notices must display the words "Powered by Projects4Me".
 */

namespace Foundation\Mvc\Model\Migration;

use Foundation\metaManager;
use Foundation\fileHandler;
use Foundation\Mvc\Model\Migration as foundationMigration;

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