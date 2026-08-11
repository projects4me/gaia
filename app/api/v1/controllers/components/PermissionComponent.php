<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Gaia\Libraries\Utils\Util;
use Phalcon\Events\Event as Event;

/**
 * This class is used to normalize permission payload values before persistence.
 *
 * @class   PermissionComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class PermissionComponent
{
    /**
     * Normalize action-permission payload before create.
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

        $model->resourceName = $modelAttributes['resourceName'] ?? $model->resourceName;
        $model->allowed = isset($modelAttributes['allowed']) ? (string) $modelAttributes['allowed'] : $model->allowed;
        if ($model->allowed === '') {
            $model->allowed = '0';
        }

        // Preserve server-generated id when the client omits one (idempotent test creates).
        if (!empty($requestValues['data']['id'])) {
            $model->newId = $model->id = $requestValues['data']['id'];
        }
    }
}
