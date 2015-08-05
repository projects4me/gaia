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

namespace Foundation;
use Phalcon\Config as PhalconConfig;

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
        $customConfig = new PhalconConfig($custom);
        
        // Merge them
        $settings->merge($customConfig);
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
        return $this->readFolder('../foundation/config/');
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
        return $this->readFolder('../config/');        
    }
    
    protected function readFolder($folder){
        $config = array();
        if (is_dir($folder)){
            $files = array();
            $files = scandir($folder,0);
            if (is_array($files)){
                $allowed_extenstions = array('.php');
                foreach($files as $file)
                {
                    $file_ext = substr($file, (strlen($file)-4),strlen($file));
                    if (in_array($file_ext,$allowed_extenstions))
                    {
                        require($folder.'/'.$file);
                    }
                }
            }
        }
        return $config;
    }

}