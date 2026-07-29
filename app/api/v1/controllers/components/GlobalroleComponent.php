<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event as Event;
use Gaia\MVC\Models\Userrole;
use Gaia\MVC\Models\Role;

use function Gaia\Libraries\Utils\create_guid as create_guid;

/**
 * This class is used to add "Global" role membership to a newly created user.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Component
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GlobalroleComponent
{
    /**
     * This function is called before a user is created and is used to create the global role if it doesn't exist.
     *
     * @param  Event $event
     * @param  $controller
     * @param  $model
     */
    public function beforeCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Globalrole::beforeCreate()');

        $role = Role::findFirstByName('Global');
        if (!$role) {
            $role = new Role();
            $role->id = create_guid();
            $role->name = 'Global';
            $role->description = 'Global role';
            $role->save();
        }

        $logger->debug('-Gaia.Controller.Component.Globalrole::beforeCreate()');
    }

    /**
     * This function handles the creation of a user role assignment for a newly created user, against the role named as "Global".
     *
     * @param  Event $event
     * @param  $controller
     * @param  $model
     * @throws \Gaia\Exception\Exception
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Globalrole::afterCreate()');

        $roleQueryBuilder = $controller->getDI()->get('modelsManager')->createBuilder();
        $roleQueryBuilder->columns(["Role.id"]);
        $roleQueryBuilder->from(['Role' => 'Gaia\\MVC\\Models\\Role']);
        $roleQueryBuilder->where("Role.name = 'Global'");
        $role = $roleQueryBuilder->getQuery()->getSingleResult();

        if ($role->id) {
            $userRole = new Userrole();
            $userRole->id = create_guid();
            $userRole->roleId = $role->id;
            $userRole->userId = $model->id;
            $userRole->save();
        } else {
            throw new \Gaia\Exception\Exception('Global role not found.');
        }

        $logger->debug('-Gaia.Controller.Component.Globalrole::afterCreate()');
    }
}
