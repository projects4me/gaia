<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event as Event;

/**
 * This class is used to add some changes in permission model against the user requested values.
 *
 * @class PermissionComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class PermissionComponent
{
    /**
     * This method is triggered before creating the permission model and is used to fetch the resource model against the
     * given name of resource and add the id of the resource inside the permission model that is going to be created.
     *
     * @param Event $event
     * @param $controller
     * @param $model
     * @method beforeCreate
     */
    public function beforeCreate(Event $event, $controller, $model)
    {
        $values = $model->requestValues;
        if (!$model->resourceId) {
            $resource = \Gaia\MVC\Models\Resource::findFirst("entity='{$values['resourceName']}'");
            $model->resourceId = $resource->id;
        }
    }
}
