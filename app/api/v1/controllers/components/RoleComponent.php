<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Gaia\Libraries\Security\RolePermissionSeeder;
use Phalcon\Events\Event as Event;

/**
 * Seeds the full ACL permission catalog when a role is created.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RoleComponent
{
    /**
     * Materialize catalog permissions for the new role from the current resolution mode.
     *
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model
     * @return void
     * @throws \Throwable
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Role::afterCreate()');

        $roleId = isset($model->id) ? (string) $model->id : '';
        if ($roleId === '' && isset($model->newId)) {
            $roleId = (string) $model->newId;
        }
        if ($roleId === '') {
            throw new \Gaia\Exception\Exception('Cannot seed permissions: role id is missing');
        }

        RolePermissionSeeder::seedFullCatalog($roleId);

        $logger->debug('-Gaia.Controller.Component.Role::afterCreate()');
    }
}
