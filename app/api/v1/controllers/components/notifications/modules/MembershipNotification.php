<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Modules;

use Gaia\MVC\Models\Project;
use Gaia\MVC\Models\User;
use Gaia\MVC\Models\Role;
use Gaia\Workflows\Actions\CreateModel;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * Membership Notification Module - Handles notifications for project membership events including creation, updates, and deletion.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Modules
 * @category Notifications
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MembershipNotification implements NotificationModuleInterface
{
    /**
     * Service for managing notification recipients
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
     * Handles notification when a new project membership is created - Creates notifications for project members and the newly added user.
     *
     * @param  object $model The membership model instance
     * @return object|null The created notification or null if not applicable
     * @throws \Gaia\Exception\Exception If notification creation fails
     */
    public function onCreate($model)
    {
        global $logger, $currentUser;
        try {
            $logger->debug('MembershipNotification::onCreate()');

            if ($model->relatedTo !== 'project') {
                return null;
            }

            $user = User::findFirstById($model->userId);
            $role = Role::findFirstById($model->roleId);
            $project = Project::findFirstById($model->relatedId);

            // First notification - to project members
            $notificationData = [
                'description' => "{{createdUser:{$currentUser->id}}} has added {{User@{$user->id}}} to {{Project@{$project->id}}} as {$role->name}",
                'context' => json_encode(
                    [
                    "projectShortcode" => $project->shortCode,
                    "projectName" => $project->name,
                    "userId" => $user->id,
                    "userName" => $user->name,
                    'relatedTo' => 'project',
                    ]
                ),
            ];
            $notification = CreateModel::execute('systemnotification', $notificationData);
            $this->recipientService->notifyProjectRecipients($notification, $project, [$user->id]);

            if ($currentUser->id !== $user->id) {
                // Second notification - to the user who was added
                $notificationData = [
                    'description' => "{{createdUser:{$currentUser->id}}} has added you to {{Project@{$project->id}}} as {$role->name}",
                    'context' => json_encode(
                        [
                        "projectShortcode" => $project->shortCode,
                        "projectName" => $project->name,
                        'relatedTo' => 'project'
                        ]
                    ),
                ];
                $notification = CreateModel::execute('systemnotification', $notificationData);

                $notificationRecipientData = [
                    'systemNotificationId' => $notification->id,
                    'userId' => $model->userId
                ];
                $notificationRecipient = CreateModel::execute('systemnotificationrecipient', $notificationRecipientData);
            }

            $logger->debug('-MembershipNotification::onCreate()');
            return $notification;
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in MembershipNotification::onCreate: ' . $e->getMessage());
        }
    }

    /**
     * Handles notification when a project membership role is updated - Creates a notification to the affected user about their role change.
     *
     * @param  object $model The membership model instance
     * @return object|null The created notification or null if not applicable
     * @throws \Gaia\Exception\Exception If notification creation fails
     */
    public function onUpdate($model)
    {
        global $logger, $currentUser;
        try {
            $logger->debug('MembershipNotification::onUpdate()');

            if ($model->relatedTo !== 'project') {
                return null;
            }

            if (!isset($model->audit['roleId'])) {
                return null;
            }

            $user = User::findFirstById($model->userId);
            $oldRole = Role::findFirstById($model->audit['roleId']['old']);
            $newRole = Role::findFirstById($model->audit['roleId']['new']);
            $project = Project::findFirstById($model->relatedId);

            $notificationData = [
                'description' => "{{createdUser:{$currentUser->id}}} has updated your role in {{Project@{$project->id}}} from {$oldRole->name} to {$newRole->name}",
                'context' => json_encode(
                    [
                    "projectShortcode" => $project->shortCode,
                    "projectName" => $project->name,
                    'relatedTo' => 'project'
                    ]
                ),
            ];
            $notification = CreateModel::execute('systemnotification', $notificationData);

            $notificationRecipientData = [
                'systemNotificationId' => $notification->id,
                'userId' => $model->userId
            ];
            $notificationRecipient = CreateModel::execute('systemnotificationrecipient', $notificationRecipientData);

            $logger->debug('-MembershipNotification::onUpdate()');
            return $notification;
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in MembershipNotification::onUpdate: ' . $e->getMessage());
        }
    }

    /**
     * Handles notification when a project membership is deleted - Creates a notification to the removed user if different from current user.
     *
     * @param  object $model The membership model instance
     * @return object|null The created notification or null if not applicable
     * @throws \Gaia\Exception\Exception If notification creation fails
     */
    public function onDelete($model)
    {
        global $logger, $currentUser;
        try {
            $logger->debug('MembershipNotification::onDelete()');

            if ($model->relatedTo !== 'project') {
                return null;
            }

            $user = User::findFirstById($model->userId);
            $project = Project::findFirstById($model->relatedId);

            if ($currentUser->id !== $user->id) {
                $notificationData = [
                    'description' => "{{createdUser:{$currentUser->id}}} has removed you from {{Project@{$project->id}}}",
                    'context' => json_encode(
                        [
                        "projectShortcode" => $project->shortCode,
                        "projectName" => $project->name,
                        'relatedTo' => 'project'
                        ]
                    ),
                ];
                $notification = CreateModel::execute('systemnotification', $notificationData);

                $notificationRecipientData = [
                    'systemNotificationId' => $notification->id,
                    'userId' => $model->userId
                ];
                $notificationRecipient = CreateModel::execute('systemnotificationrecipient', $notificationRecipientData);
                $logger->debug('-MembershipNotification::onDelete()');
                return $notification;
            }

            $logger->debug('-MembershipNotification::onDelete()');
            return null;
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in MembershipNotification::onDelete: ' . $e->getMessage());
        }
    }
}
