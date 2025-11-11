<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Gaia\Libraries\Utils\Util;
use Phalcon\Events\Event as Event;
use Gaia\MVC\Models\Membership;
use Gaia\MVC\Models\Role;
use function Gaia\Libraries\Utils\create_guid as create_guid;
use Gaia\Workflows\Actions\CreateModel;
use Gaia\MVC\REST\Controllers\Components\Notifications\Modules\MembershipNotification;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * This class is used to onboard a new member to the project before the project is created.
 *
 * @class   OnboardMemberComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class OnboardMemberComponent
{
    /**
     * This method is triggered before creating the permission model and is used to fetch the resource model against the
     * given name of resource and add the id of the resource inside the permission model that is going to be created.
     *
     * @param  Event                      $event      The event object.
     * @param  \Gaia\MVC\Rest\Controllers $controller The controller object which fire this event.
     * @param  \Gaia\MVC\Models           $model      The model object.
     * @method beforeCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.OnboardMember::afterCreate()');

        // Get role for the first membership
        $role = Role::findFirstByName('Project Manager');
        if (!$role) {
            throw new \Gaia\Exception\Exception('Project manager role not found, to add project manager to the project, you need to create a role named "Project Manager" first');
        }

        $membership = CreateModel::execute('Membership', [
            'userId' => $model->projectManager,
            'relatedId' => $model->id,
            'relatedTo' => 'project',
            'roleId' => $role->id,
        ]);
        $recipientService = new RecipientService();
        $membershipNotification = new MembershipNotification($recipientService);
        $membershipNotification->onCreate($membership);
        $logger->debug('-Gaia.Controller.Component.OnboardMember::afterCreate()');
    }
}
