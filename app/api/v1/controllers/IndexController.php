<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
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
