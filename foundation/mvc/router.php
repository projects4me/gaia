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
                                $this->addDelete($path,array('controller'=> $module,'action' => 'delete'));

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
