<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Modules;

use Gaia\MVC\Models\Project;
use Gaia\Workflows\Actions\CreateModel;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * Milestone Notification Module
 *
 * Handles notifications for milestone-related events including creation,
 * updates and deletion of milestones within projects.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Modules
 * @category Notification
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MilestoneNotification implements NotificationModuleInterface
{
    /**
     * The service responsible for managing notification recipients
     *
     * @var RecipientService
     */
    protected $recipientService;

    /**
     * Constructor
     *
     * @param RecipientService $recipientService Service for managing notification recipients
     */
    public function __construct(RecipientService $recipientService)
    {
        $this->recipientService = $recipientService;
    }

    /**
     * Handle milestone creation notification
     *
     * Creates a notification when a milestone is created and notifies all project members.
     *
     * @param  object $model The milestone model that was created
     * @return object|null The created notification or null on error
     * @throws \Gaia\Exception\Exception If an error occurs during notification creation
     */
    public function onCreate($model)
    {
        global $logger, $currentUser;
        try {
            $logger->debug('MilestoneNotification::onCreate()');

            $project = Project::findFirstById($model->projectId);

            $notificationData = [
                'description' => "{{User@{$currentUser->id}}} has created {{Milestone@{$model->id}}} milestone for {{Project@{$project->id}}}",
                'context' => json_encode(
                    [
                    "projectShortcode" => $project->shortCode,
                    "projectName" => $project->name,
                    'milestoneName' => $model->name,
                    'milestoneId' => $model->id,
                    'userId' => $currentUser->id,
                    'userName' => $currentUser->name,
                    'relatedTo' => 'milestone'
                    ]
                ),
            ];

            $notification = CreateModel::execute('systemnotification', $notificationData);
            $this->recipientService->notifyProjectRecipients($notification, $project);

            $logger->debug('-MilestoneNotification::onCreate()');
            return $notification;
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in MilestoneNotification::onCreate: ' . $e->getMessage());
        }
    }

    /**
     * Handle milestone update notification
     *
     * Currently not implemented - placeholder for future functionality.
     *
     * @param  object $model The milestone model that was updated
     * @return null No notification is currently created for milestone updates
     */
    public function onUpdate($model)
    {
        // Milestone update notifications not currently implemented
        return null;
    }

    /**
     * Handle milestone deletion notification
     *
     * Currently not implemented - placeholder for future functionality.
     *
     * @param  object $model The milestone model that was deleted
     * @return null No notification is currently created for milestone deletion
     */
    public function onDelete($model)
    {
        // Milestone delete notifications not currently implemented
        return null;
    }
}
