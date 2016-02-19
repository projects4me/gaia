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

class IndexController extends \Phalcon\Mvc\Controller
{

    public function indexAction()
    {
        global $settings;
        
        // Basec on the environment load the required files
        
        // If the environment is development then load all the javascripts
        // otherwise load the combined production files
        switch ($settings->application->env)
        {
            case 'prod':
            case 'production':
            case 'live':
                /**
                 * @todo Load the combined files if exsists otherwise generate
                 */
                break;
            
            case 'dev':    
            case 'development':
            case 'testing':
            case 'qa':
                // load all the js files from public js
                $jsFiles = $cssFiles = array();
                $Directory = new RecursiveDirectoryIterator(APP_PATH.'/public/libs');
                $Iterator = new RecursiveIteratorIterator($Directory);
                $Regex = new RegexIterator($Iterator, '/^.+\.js$/i', RecursiveRegexIterator::GET_MATCH);
                foreach($Regex as $jsfile)
                {
                    /**
                     * @todo add support for paths ending with / as well
                     */
                    $path = str_replace(APP_PATH.'/public/', $settings->application->siteURL, $jsfile[0]);
                    $jsFiles[] = $path;
                }
                sort($jsFiles);

                $Directory = new RecursiveDirectoryIterator(APP_PATH.'/public/foundation');
                $Iterator = new RecursiveIteratorIterator($Directory);
                $Regex = new RegexIterator($Iterator, '/^.+\.js$/i', RecursiveRegexIterator::GET_MATCH);
                foreach($Regex as $jsfile)
                {
                    /**
                     * @todo add support for paths ending with / as well
                     */
                    $path = str_replace(APP_PATH.'/public/', $settings->application->siteURL, $jsfile[0]);
                    $jsFiles[] = $path;
                }

                $Directory = new RecursiveDirectoryIterator(APP_PATH.'/public/app');
                $Iterator = new RecursiveIteratorIterator($Directory);
                $Regex = new RegexIterator($Iterator, '/^.+\.js$/i', RecursiveRegexIterator::GET_MATCH);
                foreach($Regex as $jsfile)
                {
                    /**
                     * @todo add support for paths ending with / as well
                     */
                    $path = str_replace(APP_PATH.'/public/', $settings->application->siteURL, $jsfile[0]);
                    $jsFiles[] = $path;
                }

                
                /**
                 * @todo Create function that will help load the file path names
                 */
                $Directory = new RecursiveDirectoryIterator(APP_PATH.'/public/css');
                $Iterator = new RecursiveIteratorIterator($Directory);
                $Regex = new RegexIterator($Iterator, '/^.+\.css$/i', RecursiveRegexIterator::GET_MATCH);
                foreach($Regex as $cssfile)
                {
                    /**
                     * @todo add support for paths ending with / as well
                     */
                    $path = str_replace(APP_PATH.'/public/', $settings->application->siteURL, $cssfile[0]);
                    $cssFiles[] = $path;
                }
                sort($cssFiles);
                $cssFiles[] = 'vendors/scrollbar/jquery.mCustomScrollbar.min.css';
                $jsFiles[] = 'vendors/scrollbar/jquery.mCustomScrollbar.concat.min.js';
                //$jsFiles[] = 'vendors/ember-i18n-3.1.1//lib/i18n.js';
                
                $this->view->jsFiles = $jsFiles;
                $this->view->cssFiles = $cssFiles;                
                break;
            default:
                throw new \Phalcon\Exception('Environment missing in the application configuration');
                break;
            
        }
    }

}
