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
    'rest' => array(
        'v1' => array(
            'activity' => array(
                'path' => '/api/:version/activity',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'comment' => array(
                'path' => '/api/:version/comment',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'conversationroom' => array(
                'path' => '/api/:version/conversationroom',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'converser' => array(
                'path' => '/api/:version/converser',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'issue' => array(
                'path' => '/api/:version/issue',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'issuetype' => array(
                'path' => '/api/:version/issuetype',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'membership' => array(
                'path' => '/api/:version/membership',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'milestone' => array(
                'path' => '/api/:version/milestone',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'mention' => array(
                'path' => '/api/:version/mention',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'timelog' => array(
                'path' => '/api/:version/timelog',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'role' => array(
                'path' => '/api/:version/role',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'project' => array(
                'path' => '/api/:version/project',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'user' => array(
                'path' => '/api/:version/user',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'resource' => array(
                'path' => '/api/:version/resource',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'permissions' => array(
                'path' => '/api/:version/permissions',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'token' => array(
                'path' => '/api/:version/token',
                'allowedMethods' => array(
                    'POST',
                ),
                'identifier' => 'id',
            ),
            'tag' => array(
                'path' => '/api/:version/tag',
                'allowedMethods' => array(
                  'GET',
                  'POST',
                  'PUT',
                  'DELETE',
                  'PATCH',
                ),
                'identifier' => 'id',
            ),
            'wiki' => array(
                'path' => '/api/:version/wiki',
                'allowedMethods' => array(
                  'GET',
                  'POST',
                  'PUT',
                  'DELETE',
                  'PATCH',
                ),
                'identifier' => 'id',
            ),
            'vote' => array(
                'path' => '/api/:version/vote',
                'allowedMethods' => array(
                  'GET',
                  'POST',
                  'PUT',
                  'DELETE',
                  'PATCH',
                ),
                'identifier' => 'id',
            )
        )
    ),
    'app' => array(
        'index' => array(
            'path' => '/',
            'controller' => 'index',
            'action' => 'index',
        ),
        'image' => array(
            'path' => '/image',
            'controller' => 'userimage',
            'action' => 'get',
        )
    ),
    'system' => array(
        'not_found' => array(
            'controller' => 'error',
            'action' => 'not_found',
        ),
        'default' => array(
            'controller' => 'index',
            'action' => 'index',
        )
    )
);
/**/

return $config;
