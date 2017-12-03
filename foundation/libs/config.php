<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Foundation;
use Phalcon\Config as PhalconConfig;
require_once APP_PATH.'/foundation/libs/fileHandler.php';

/**
 * This is the config class in the foundation package. The class manages the
 * configurations in the application. It uses Phalcon\Config and is responsible
 * for reading all the default and custom configurations and exposing them
 * through a global variable.
 * 
 * @param array $config this configuration in the system
 * 
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Config
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Config
{
    protected $config;
    
    /**
     * This function is responsible for initializing the configurations and
     * populating them in the glocal variable $config.
     * 
     * @todo make sure that the merge allows config to be overwritten as well
     */
    public function init()
    {
       
        global $settings;
        /* First load all the default configurations        */
        $default = $this->loadDefault();
        
        /* Next load all the custom configurations        */
        $custom = $this->loadCustom();
        
        // Initiate the routes
        $settings = new PhalconConfig($default);
        $customSettings = new PhalconConfig($custom);
        
        // Merge them
        $settings->merge($customSettings);
    }
    
    /**
     * This function is responsible including the necassary default 
     * configurations
     * 
     * @return array default configurations
     * @todo merge all the files in folder to one files in future
     */
    protected function loadDefault(){
        // Loading all the files in foundation/config/
        return fileHandler::readFolder(APP_PATH.'/foundation/config/');
    }
    
    /**
     * This function is responsible including the necassary custom 
     * configurations, if any
     * 
     * @return array custom configurations
     * @todo merge all the files in folder to one files in future
     */
    protected function loadCustom(){
        // Loading all the files in config/
        return fileHandler::readFolder(APP_PATH.'/config/');        
    }
    


}
