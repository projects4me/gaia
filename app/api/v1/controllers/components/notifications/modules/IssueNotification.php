<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Modules;

use Gaia\MVC\Models\Project;
use Gaia\MVC\Models\User;
use Gaia\Workflows\Actions\CreateModel;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * IssueNotification Class
 *
 * This class handles the notifications related to issue events such as creation, update, and deletion.
 * It implements the NotificationModuleInterface and utilizes the RecipientService to send notifications
 * to relevant users based on the changes in the issue model.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Modules
 * @category Notifications
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueNotification implements NotificationModuleInterface
{
    /**
     * @var RecipientService The service used to notify users about issue events.
     */
    protected $recipientService;

    /**
     * Constructor to initialize the recipient service.
     *
     * @param RecipientService $recipientService The service used for sending notifications.
     */
    public function __construct(RecipientService $recipientService)
    {
        $this->recipientService = $recipientService;
    }

    /**
     * Handle issue creation notification
     *
     * @param  object $model The issue model that was created.
     * @throws \Gaia\Exception\Exception If an error occurs during notification.
     */
    public function onCreate($model)
    {
        global $logger, $currentUser;
        try {
            $logger->debug('IssueNotification::onCreate()');

            $project = Project::findFirstById($model->projectId);

            // Notify assignee if different from current user
            if ($model->assignee && $model->assignee !== $currentUser->id) {
                $notificationData = [
                    'description' => "{{User@{$currentUser->id}}} has assigned issue {{Issue@{$model->id}}} to you",
                    'context' => json_encode(
                        [
                        "projectShortcode" => $project->shortCode,
                        "projectName" => $project->name,
                        "issueNumber" => $model->issueNumber,
                        "userName" => $currentUser->name,
                        "userId" => $currentUser->id,
                        'relatedTo' => 'issue'
                        ]
                    ),
                ];
                $notification = CreateModel::execute('systemnotification', $notificationData);
                $this->recipientService->notifyUsers($notification, [$model->assignee]);
            }

            // Notify owner if different from current user
            if ($model->owner && $model->owner !== $currentUser->id) {
                $notificationData = [
                    'description' => "{{User@{$currentUser->id}}} has made you the owner of {{Issue@{$model->id}}}",
                    'context' => json_encode(
                        [
                        "projectShortcode" => $project->shortCode,
                        "projectName" => $project->name,
                        "issueNumber" => $model->issueNumber,
                        "userName" => $currentUser->name,
                        "userId" => $currentUser->id,
                        'relatedTo' => 'issue'
                        ]
                    ),
                ];
                $notification = CreateModel::execute('systemnotification', $notificationData);
                $this->recipientService->notifyUsers($notification, [$model->owner]);
            }

            $logger->debug('-IssueNotification::onCreate()');
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in IssueNotification::onCreate: ' . $e->getMessage());
        }
    }

    /**
     * Handle issue update notification
     *
     * @param  object $model The issue model that was updated.
     * @throws \Gaia\Exception\Exception If an error occurs during notification.
     */
    public function onUpdate($model)
    {
        global $logger, $currentUser;
        try {
            $logger->debug('IssueNotification::onUpdate()');

            // Check if status was changed
            if (isset($model->audit['status']) && $model->audit['status']['old'] !== $model->audit['status']['new']) {
                $this->handleStatusChange($model);
            }

            // Check if issue assignee is changed
            if (isset($model->audit['assignee']) && $model->audit['assignee']['old'] !== $model->audit['assignee']['new']) {
                $this->handleAssigneeChange($model);
            }

            // Check if issue owner is changed
            $this->handleOwnerChange($model);

            $logger->debug('-IssueNotification::onUpdate()');
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in IssueNotification::onUpdate: ' . $e->getMessage());
        }
    }

    /**
     * Handle issue deletion notification
     *
     * @param  object $model The issue model that was deleted.
     * @return null Currently, issue deletion notifications are not implemented.
     */
    public function onDelete($model)
    {
        // Issue deletion notifications not currently implemented
        return null;
    }

    /**
     * Handle status change notification
     *
     * @param  object $model The issue model with updated status.
     * @return object The notification object created.
     */
    protected function handleStatusChange($model)
    {
        global $logger, $currentUser;

        $project = Project::findFirstById($model->projectId);
        $user = User::findFirstById($currentUser->id);

        $notificationData = [
            'description' => "{{User@{$currentUser->id}}} has updated the {{Issue@{$model->id}}} status to {{status:{$model->status}}}",
            'context' => json_encode(
                [
                "projectShortcode" => $project->shortCode,
                "issueNumber" => $model->issueNumber,
                "issueStatus" => $model->status,
                "userId" => $user->id,
                "userName" => $user->name,
                'relatedTo' => 'issue'
                ]
            ),
        ];

        $notification = CreateModel::execute('systemnotification', $notificationData);
        $this->recipientService->notifyIssueRecipients($notification, $model);

        return $notification;
    }

    /**
     * Handle assignee change notification
     *
     * @param  object $model The issue model with updated assignee.
     * @return object The notification object created.
     */
    protected function handleAssigneeChange($model)
    {
        global $logger, $currentUser;

        $project = Project::findFirstById($model->projectId);
        $user = User::findFirstById($currentUser->id);
        $oldAssigneeId = $model->audit['assignee']['old'];
        $newAssigneeId = $model->audit['assignee']['new'];
        $notificationData = [
            'description' => "{{User@{$currentUser->id}}} has updated the {{Issue@{$model->id}}} assignee to {{User@{$newAssigneeId}}}",
            'context' => json_encode(
                [
                "projectShortcode" => $project->shortCode,
                "issueNumber" => $model->issueNumber,
                "userId" => $user->id,
                "userName" => $user->name,
                'relatedTo' => 'issue'
                ]
            ),
        ];

        $notification = CreateModel::execute('systemnotification', $notificationData);
        $this->recipientService->notifyIssueRecipients($notification, $model, [$newAssigneeId]);

        $this->notifyNewAssignee($model);
        $this->notifyOldAssignee($model);

        return $notification;
    }

    /**
     * Handle owner change notification
     *
     * @param object $model The issue model with updated owner.
     */
    protected function handleOwnerChange($model)
    {
        global $currentUser, $logger;
        
        if (isset($model->audit['owner']) && $model->audit['owner']['old'] !== $model->audit['owner']['new']) {
            $project = Project::findFirstById($model->projectId);
            $newOwnerId = $model->audit['owner']['new'];
            $oldOwnerId = $model->audit['owner']['old'];

            // Notify new owner if different from current user
            if ($newOwnerId && $newOwnerId !== $currentUser->id) {
                $notificationData = [
                    'description' => "{{User@{$currentUser->id}}} has made you the owner of {{Issue@{$model->id}}}",
                    'context' => json_encode(
                        [
                        "projectShortcode" => $project->shortCode,
                        "projectName" => $project->name,
                        "issueNumber" => $model->issueNumber,
                        "userName" => $currentUser->name,
                        "userId" => $currentUser->id,
                        'relatedTo' => 'issue'
                        ]
                    ),
                ];
                $notification = CreateModel::execute('systemnotification', $notificationData);
                $this->recipientService->notifyUsers($notification, [$newOwnerId]);
            }

            // Notify old owner if different from current user
            if ($oldOwnerId && $oldOwnerId !== $currentUser->id) {
                $notificationData = [
                    'description' => "{{User@{$currentUser->id}}} has removed you as the owner from {{Issue@{$model->id}}}",
                    'context' => json_encode(
                        [
                        "projectShortcode" => $project->shortCode,
                        "projectName" => $project->name,
                        "issueNumber" => $model->issueNumber,
                        "userName" => $currentUser->name,
                        "userId" => $currentUser->id,
                        'relatedTo' => 'issue'
                        ]
                    ),
                ];
                $notification = CreateModel::execute('systemnotification', $notificationData);
                $this->recipientService->notifyUsers($notification, [$oldOwnerId]);
            }
        }
    }

    /**
     * Notify the new assignee about the issue assignment
     *
     * @param object $model The issue model with updated assignee.
     */
    protected function notifyNewAssignee($model)
    {
        global $logger, $currentUser;

        $project = Project::findFirstById($model->projectId);
        $user = User::findFirstById($currentUser->id);
        $newAssigneeId = $model->audit['assignee']['new'];

        $notificationData = [
            'description' => "{{User@{$currentUser->id}}} has assigned you {{Issue@{$model->id}}}",
            'context' => json_encode(
                [
                "projectShortcode" => $project->shortCode,
                "issueNumber" => $model->issueNumber,
                "userId" => $user->id,
                "userName" => $user->name,
                'relatedTo' => 'issue'
                ]
            ),
        ];

        $notification = CreateModel::execute('systemnotification', $notificationData);
        $this->recipientService->notifyUsers($notification, [$newAssigneeId]);
    }

    /**
     * Notify the old assignee about the removal from the issue
     *
     * @param  object $model The issue model with updated assignee.
     * @return object The notification object created.
     */
    protected function notifyOldAssignee($model)
    {
        global $logger, $currentUser;

        $project = Project::findFirstById($model->projectId);
        $user = User::findFirstById($currentUser->id);
        $oldAssigneeId = $model->audit['assignee']['old'];
        $notificationData = [
            'description' => "{{User@{$currentUser->id}}} has removed you as the assignee from {{Issue@{$model->id}}}",
            'context' => json_encode(
                [
                "projectShortcode" => $project->shortCode,
                "issueNumber" => $model->issueNumber,
                "userId" => $user->id,
                "userName" => $user->name,
                'relatedTo' => 'issue'
                ]
            ),
        ];

        $notification = CreateModel::execute('systemnotification', $notificationData);
        $this->recipientService->notifyUsers($notification, [$oldAssigneeId]);

        return $notification;
    }
}
