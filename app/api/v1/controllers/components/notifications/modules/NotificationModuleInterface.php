<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Modules;

use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * Notification Module Interface
 *
 * Defines the contract that all notification modules must implement.
 * This ensures consistent handling of create, update, and delete events
 * across different entity types in the system.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Modules
 * @category Interface
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface NotificationModuleInterface
{
    /**
     * Constructor
     *
     * @param RecipientService $recipientService Service for managing notification recipients
     */
    public function __construct(RecipientService $recipientService);

    /**
     * Generates and sends notifications when a new entity is created.
     *
     * @param  object $model The model that was created
     * @return mixed The notification object or result
     */
    public function onCreate($model);

    /**
     * Generates and sends notifications when an entity is updated.
     *
     * @param  object $model The model that was updated
     * @return mixed The notification object or result
     */
    public function onUpdate($model);

    /**
     * Generates and sends notifications when an entity is deleted.
     *
     * @param  object $model The model that was deleted
     * @return mixed The notification object or result
     */
    public function onDelete($model);
}
