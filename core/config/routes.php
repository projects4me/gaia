<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
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
            'chatroom' => array(
                'path' => '/api/:version/chatroom',
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
            'savedsearch' => array(
                'path' => '/api/:version/savedsearch',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'tagged' => array(
                'path' => '/api/:version/tagged',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'DELETE',
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
            ),
            'upload' => array(
                'path' => '/api/:version/upload',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'badge' => array(
                'path' => '/api/:version/badge',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH'
                )
            ),
            'scoreboard' => array(
                'path' => '/api/:version/scoreboard',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH'
                )
            ),
            'issuestatus' => array(
                'path' => '/api/:version/issuestatus',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH',
                ),
                'identifier' => 'id',
            ),
            'userimage' => array(
                'path' => '/api/:version/userimage',
                'allowedMethods' => array(
                    'POST'
                ),
                'identifier' => 'id'
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
        ),
        'download' => array(
            'path' => '/download',
            'controller' => 'download',
            'action' => 'get',
        ),
        'preview' => array(
            'path' => '/preview',
            'controller' => 'preview',
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
