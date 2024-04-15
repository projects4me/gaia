<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event as Event;
use Gaia\MVC\Models\Membership;

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
     * This function handles the creation of membership for a newly created user, against the role named as "Global".
     *
     * @param  Event $event
     * @param  $controller
     * @throws \Gaia\Exception\Exception
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Globalrole::afterCreate()');

        // Get Role
        $roleQueryBuilder = $controller->getDI()->get('modelsManager')->createBuilder();
        $roleQueryBuilder->columns(["Role.id"]);
        $roleQueryBuilder->from(['Role' => 'Gaia\\MVC\\Models\\Role']);
        $roleQueryBuilder->where("Role.name = 'Global'");
        $role = $roleQueryBuilder->getQuery()->getSingleResult();

        // Add user membership for Global role.
        if ($role->id) {
            $membership = new Membership();
            $membership->id = create_guid();
            $membership->roleId = $role->id;
            $membership->userId = $model->id;
            $membership->relatedTo = 'system';
            $membership->save();
        } else {
            throw new \Gaia\Exception\Exception('Global role not found.');
        }

        $logger->debug('-Gaia.Controller.Component.Globalrole::afterCreate()');
    }
}
