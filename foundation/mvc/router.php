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

        // Traversing through the routes library set in the settings
        // throwing an exception if no routes were found
        if (isset($settings->routes) && !empty($settings->routes))
        {
            foreach($settings->routes as $approute)
            {
                // Initializing 'method' as it is optional and can be blank
                $method = '';
                if (isset($approute->method))
                {
                    $method = $approute->method;
                    // unsetting the method from the object so that we can pass
                    // the same array to the Phalcon\Config class
                    unset($approute->method);
                }

                // Initializing 'path' as it is optional and can be blank
                $path = '';
                if (isset($approute->path))
                {
                    $path = $approute->path;
                    // unsetting the path from the object so that we can pass
                    // the same array to the Phalcon\Config class
                    unset($approute->path);
                }

                //type is required and we need to make decision on it
                // possible values for type are rest|app|system
                $type = $approute->type;
                // unsetting the type from the object so that we can pass
                // the same array to the Phalcon\Config class
                unset($approute->type);

                // processing all the REST routes
                if ($type == 'rest')
                {
                    // swtiching on the bases on method, the supported values
                    // are GET|POST|PUT|PATCH|DELETE|OPTIONS
                    switch ($method)
                    {
                        case 'GET':
                        $this->addGet($path,(array) $approute);
                        break;

                        case 'POST':
                        $this->addPost($path,(array) $approute);
                        break;

                        case 'PUT':
                        $this->addPut($path,(array) $approute);
                        break;

                        case 'PATCH':
                        $this->addPatch($path,(array) $approute);
                        break;

                        case 'DELETE':
                        $this->addDelete($path,(array) $approute);
                        break;
                    }
                }
                // processing all the system level routes
                else if ($type == 'system')
                {
                    switch ($method)
                    {
                        case 'notfound':
                        $this->notFound((array) $approute);
                        break;

                        case 'default':
                        $this->setDefaults((array) $approute);
                        break;
                    }
                }
                // processing all the application level routes
                else if($type == 'app') {
                    $this->add($path,(array) $approute);
                }
            }// end -foreach
        }
        else {
            //@todo throw/log error
        } // end if
        
    }
}
