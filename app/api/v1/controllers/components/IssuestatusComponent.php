<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event as Event;

/**
 * Saves and deletes the translated labels of an issue status with the status.
 *
 * IssuestatusController runs the request in one transaction, so a label that
 * cannot be written (the exception thrown here) rolls the status back too.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssuestatusComponent
{
    /**
     * Save the labels the new status was created with.
     *
     * @param  Event                                            $event
     * @param  \Gaia\MVC\REST\Controllers\IssuestatusController $controller
     * @param  \Gaia\MVC\Models\Issuestatus                     $model
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->saveRequestedLabels($controller, $model);
    }

    /**
     * Save the labels the update sent, if any.
     *
     * @param  Event                                            $event
     * @param  \Gaia\MVC\REST\Controllers\IssuestatusController $controller
     * @param  \Gaia\MVC\Models\Issuestatus                     $model
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $this->saveRequestedLabels($controller, $model);
    }

    /**
     * Delete the labels of the deleted status's value unless another status uses it.
     *
     * @param  Event                                            $event
     * @param  \Gaia\MVC\REST\Controllers\IssuestatusController $controller
     * @param  \Gaia\MVC\Models\Issuestatus                     $model
     * @return void
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        $controller->statusLabels()->deleteUnused($model->name);
    }

    /**
     * @param  \Gaia\MVC\REST\Controllers\IssuestatusController $controller
     * @param  \Gaia\MVC\Models\Issuestatus                     $model
     * @return void
     */
    private function saveRequestedLabels($controller, $model)
    {
        $labels = $controller->requestedTranslations();
        if (!empty($labels)) {
            $controller->statusLabels()->save($model->name, $labels);
        }
    }
}
