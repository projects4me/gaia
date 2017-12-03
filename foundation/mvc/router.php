<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Foundation\Mvc;
use Phalcon\Mvc as PhalconRouter;

/**
 * This is the router class in the foundation package. The class extends from
 * PhalconPHP's Router class and adds unto it by declaring functions required to
 * implement the REST API Routes
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation\Mvc
 * @category Router
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Router extends PhalconRouter\Router
{

    /**
     * This function is responsible for setting initializing the routes set
     * in configuration files.
     *
     */
    public function init()
    {

        // Initialiing the global settings variable to populate the data
        global $settings;

        // Removing extra slashed
        $this->removeExtraSlashes(true);

        //print_r($settings);
        // Travese all the route types
        if (isset($settings->routes) && !empty($settings->routes))
        {
            if (isset($settings->routes['rest']) && !empty($settings->routes['rest']))
            {
                // Scan all the REST routes and set them up
                foreach($settings->routes['rest'] as $version => $versionRoutes)
                {
                    // Traverse the routes for all the versions registered
                    foreach($versionRoutes as $module => $moduleRoutes)
                    {
                        // Setup the base path considering the version
                        $basepath = preg_replace('/\:version/i',$version,$moduleRoutes->path);

                        // Setup the individual methods
                        foreach($moduleRoutes->allowedMethods as $method)
                        {
                            switch ($method)
                            {
                                case 'GET':
                                // Allow retrieval of entity
                                $path = (($basepath[0] === '/')?'':'/').$basepath.'/([a-zA-Z0-9_-]+)';
                                $this->addGet($path,array('controller'=> $module,'action' => 'get', $moduleRoutes->identifier => '1'));

                                // Allow retrieval of realted records for an entity
                                $path = (($basepath[0] === '/')?'':'/').$basepath.'/([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)';
                                $this->addGet($path,array('controller'=> $module,'action' => 'related', $moduleRoutes->identifier => '1', 'relation' => '2'));

                                // Allow retrieval of Collection
                                $path = (($basepath[0] === '/')?'':'/').$basepath;
                                $this->addGet($path,array('controller'=> $module,'action' => 'list'));
                                break;

                                case 'POST':
                                /**
                                 * @todo : allow batch insertion
                                 */
                                $path = (($basepath[0] === '/')?'':'/').$basepath;
                                $this->addPost($path,array('controller'=> $module,'action' => 'post'));
                                break;

                                case 'PUT':
                                // Officialy we do not suuport the optionn but just in case
                                $path = (($basepath[0] === '/')?'':'/').$basepath.'/([a-zA-Z0-9_-]+)';
                                $this->addPut($path,array('controller'=> $module,'action' => 'put'));

                                $path = (($basepath[0] === '/')?'':'/').$basepath;
                                $this->addPut($path,array('controller'=> $module,'action' => 'putCollection'));
                                break;

                                case 'PATCH':
                                // Allow update for an independant resource
                                $path = (($basepath[0] === '/')?'':'/').$basepath.'/([a-zA-Z0-9_-]+)';
                                $this->addPatch($path,array('controller'=> $module,'action' => 'patch'));

                                // Allow update for a collection of resources
                                $path = (($basepath[0] === '/')?'':'/').$basepath;
                                $this->addPatch($path,array('controller'=> $module,'action' => 'patchCollection'));
                                break;

                                case 'DELETE':
                                // Allow deletion of an entity
                                $path = (($basepath[0] === '/')?'':'/').$basepath.'/([a-zA-Z0-9_-]+)';
                                $this->addDelete($path,array('controller'=> $module,'action' => 'delete', $moduleRoutes->identifier => '1'));

                                // Allow deletion of a collection of resource
                                $path = (($basepath[0] === '/')?'':'/').$basepath;
                                $this->addDelete($path,array('controller'=> $module,'action' => 'deleteCollection'));
                                break;
                            } // end switch on method
                        } // end foreach on $allowedMethods
                        $path = (($basepath[0] === '/')?'':'/').$basepath;
                        $this->addOptions($path,array('controller'=> $module,'action' => 'options'));
                    } // end foreach on $versionRoutes
                } // end foreach on $routes['rest']
            } // end if $routes['test'];

            // Traverse app routes
            if (isset($settings->routes['app']) && !empty($settings->routes['app']))
            {
                foreach($settings->routes['app'] as $name => $approute)
                {
                    $path = (isset($approute->path))?$approute->path:'/';
                    unset($approute->path);
                    $this->add($path,(array) $approute);
                }
            }

            // Traverse system routes
            if (isset($settings->routes['system']) && !empty($settings->routes['system']))
            {
                foreach($settings->routes['system'] as $type => $approute)
                {
                    switch ($name)
                    {
                        case 'notfound':
                        $this->notFound((array) $approute);
                        break;

                        case 'default':
                        $this->setDefaults((array) $approute);
                        break;
                    }
                }
            }

            // @todo : Allow console routes
        }

    }
}
