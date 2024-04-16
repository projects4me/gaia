<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event as Event;
use Gaia\MVC\Models\Dashboard;

use function Gaia\Libraries\Utils\create_guid as create_guid;

/**
 * This class handles the creation of dashboard for newly created user.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Component
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DashboardComponent
{
    /**
     * This function is used create dashboard model object and link it with the newly created user.
     *
     * @param  Event $event
     * @param  $controller
     * @throws \Gaia\Exception\Exception
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Dashboard::afterCreate()');

        $dashboard = new Dashboard();
        $dashboard->id = create_guid();
        $dashboard->widgets = 'issuesToday, weeklyMilestones';
        $dashboard->name = 'Default Dashboard';
        $dashboard->userId = $model->id;
        $dashboard->save();

        $logger->debug('-Gaia.Controller.Component.Dashboard::afterCreate()');
    }
}
