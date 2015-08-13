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

/**
 * These are the default routes defined by Foundation. These routes can be
 * extended or overwriten via the application through /config/routes.
 * 
 * This file will always be loaded by Foundation\Mvc\Router and includes the
 * routes required by the framework thus it is advised that these are not
 * overwritten but extended.
 * 
 * Extending the routes is very easy, this file declares the array for routes
 * by $routes = new array() funtion. It is recommended that the extension is done by
 * using the notation $routes['new_route'] => array(..properties..)
 */

/**
 * Each route must define the following
 *  - path  (optional for system && required for app and rest)
 *  - controller (required)
 *  - action (required)
 *  - type (required | possible values - app, system, rest)
 *  - method (optional)
 *  - parameters (optional,multiple)
 */


$config['routes'] = array(
    // Index route
    0 => array(
        'path' => '/',
        'controller' => 'index',
        'action' => 'index',
        'type' => 'app'
    ),
    
    // ------ REST routes

    // GET with the format /api/:controller/:string
    1 => array(
        'path' => '/api/:controller/([a-zA-Z0-9_-]+)',
        'controller' => 1,
        'action' => 'get',
        'type' => 'rest',
        'method' => 'GET',
        'id' => '2'
    ),
    
    // GET with the format /api/:controller/
    2 => array(
        'path' => '/api/:controller',
        'controller' => 1,
        'action' => 'list',
        'type' => 'rest',
        'method' => 'GET',
    ),

    // POST with the format /api/:controller
    3 => array(
        'path' => '/api/:controller/',
        'controller' => 1,
        'action' => 'save',
        'type' => 'rest',
        'method' => 'POST'
    ),

    // PUT with the format /api/:controller
    4 => array(
        'path' => '/api/:controller/([a-zA-Z0-9_-]+)',
        'controller' => 1,
        'action' => 'put',
        'type' => 'rest',
        'method' => 'PUT',
        'id' => 2
    ),

    // PATCH with the format /api/:controller
    5 => array(
        'path' => '/api/:controller/([a-zA-Z0-9_-]+)',
        'controller' => 1,
        'action' => 'patch',
        'type' => 'rest',
        'method' => 'PATCH',
        'id' => 2
    ),

    // DELETE with the format /api/:controller
    6 => array(
        'path' => '/api/:controller/([a-zA-Z0-9_-]+)',
        'controller' => 1,
        'action' => 'delete',
        'type' => 'rest',
        'method' => 'DELETE',
        'id' => 2
    ),

    // OPTIONS with the format /api/:controller
    7 => array(
        'path' => '/api/:controller/([a-zA-Z0-9_-]+)',
        'controller' => 1,
        'action' => 'options',
        'type' => 'rest',
        'method' => 'OPTIONS',
        'id' => 2
    ),

    // ------ end REST routes

    // Not Found Route
    8 => array(
        'controller' => 'error',
        'action' => 'not_found',
        'type' => 'system',
        'method' => 'notfound'
    ),

    // Default Route
    9 => array(
        'controller' => 'index',
        'action' => 'index',
        'type' => 'system',
        'method' => 'default'
    ),
);

return $config;