<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Gaia\Libraries\Utils\Util;
use Phalcon\Events\Event as Event;

/**
 * This class is used to add some changes in permission model against the user requested values.
 *
 * @class   PermissionComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class PermissionComponent
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
    public function beforeCreate(Event $event, $controller, $model)
    {
        $util = new Util();
        $requestValues = $util->objectToArray($controller->request->getJsonRawBody());
        $modelAttributes = $requestValues['data']['attributes'];

        if (!$model->resourceId || str_contains($model->resourceId, 'new')) {
            $resource = \Gaia\MVC\Models\Resource::findFirst("entity='{$modelAttributes['resourceName']}'");
            $model->resourceId = $resource->id;
            $model->resourceName = 'subject';
        }
        $model->newId = $model->id = $requestValues['data']['id'];
    }
}
