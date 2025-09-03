<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Services;

use Gaia\MVC\Models\Membership;
use Gaia\Workflows\Actions\CreateModel;

/**
 * Service for managing notification recipients. Provides methods to
 * notify issue assignees, project members, and specific users about
 * activities and updates in the system.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Services
 * @category Service
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RecipientService
{
    /**
     * Creates notification recipients for the assignee and owner of an issue,
     * excluding the current user and any explicitly excluded user IDs.
     *
     * @param  object $notification The notification object
     * @param  object $issueModel   The issue model
     * @param  array  $excludeIds   User IDs to exclude from notification (optional)
     * @return void
     * @throws \Gaia\Exception\Exception If an error occurs during recipient creation
     */
    public function notifyIssueRecipients($notification, $issueModel, $excludeIds = [], $recipients = ['assignee', 'owner', 'watchers'])
    {
        global $currentUser;
        $di = \Phalcon\Di::getDefault();
        $recipientIds = array_merge([$currentUser->id], $excludeIds);
        try {
            foreach ($recipients as $recipient) {
                $relMeta = $di->get('metaManager')->getRelationshipMeta($issueModel->modelAlias, $recipient, false);
                if (empty($relMeta)) {
                    if ($currentUser->id !== $issueModel->{$recipient} && !in_array($issueModel->{$recipient}, $recipientIds)) {
                        $notificationRecipientData = [
                            'systemNotificationId' => $notification->id,
                            'userId' => $issueModel->{$recipient}
                        ];
                        $notificationRecipient = CreateModel::execute('systemnotificationrecipient', $notificationRecipientData);
                            $recipientIds[] = $issueModel->{$recipient};
                    }
                } else {
                    $relatedModels = $relMeta['relatedModel']::findByIssueId($issueModel->id);
                    foreach ($relatedModels as $relatedModel) {
                        if ($currentUser->id !== $relatedModel->userId && !in_array($relatedModel->userId, $recipientIds)) {
                            $notificationRecipientData = [
                                'systemNotificationId' => $notification->id,
                                'userId' => $relatedModel->userId
                            ];
                            $notificationRecipient = CreateModel::execute('systemnotificationrecipient', $notificationRecipientData);
                            $recipientIds[] = $relatedModel->userId;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in notifyIssueRecipients: ' . $e->getMessage());
        }
    }

    /**
     * Creates notification recipients for all members of a project,
     * excluding the current user and any explicitly excluded user IDs.
     *
     * @param  object $notification The notification object
     * @param  object $projectModel The project model
     * @param  array  $excludeIds   User IDs to exclude from notification (optional)
     * @return void
     * @throws \Gaia\Exception\Exception If an error occurs during recipient creation
     */
    public function notifyProjectRecipients($notification, $projectModel, $excludeIds = [])
    {
        global $currentUser;
        $recipientIds = array_merge([$currentUser->id], $excludeIds);
        try {
            $memberships = Membership::find(
                [
                'conditions' => 'relatedId = :relatedId: AND relatedTo = :relatedTo:',
                'bind' => ['relatedId' => $projectModel->id, 'relatedTo' => 'project']
                ]
            );

            foreach ($memberships as $membership) {
                if ($membership->userId !== $currentUser->id && !in_array($membership->userId, $recipientIds)) {
                    $notificationRecipientData = [
                        'systemNotificationId' => $notification->id,
                        'userId' => $membership->userId
                    ];
                    $notificationRecipient = CreateModel::execute('systemnotificationrecipient', $notificationRecipientData);
                    $recipientIds[] = $membership->userId;
                }
            }
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in notifyProjectRecipients: ' . $e->getMessage());
        }
    }

    /**
     * Creates a notification recipient entry for a specific user.
     *
     * @param  string $notificationId The ID of the notification
     * @param  string $userId         The ID of the user to notify
     * @return object The created notification recipient
     * @throws \Gaia\Exception\Exception If an error occurs during recipient creation
     */
    public function addRecipient($notificationId, $userId)
    {
        try {
            $notificationRecipientData = [
                'systemNotificationId' => $notificationId,
                'userId' => $userId
            ];
            return CreateModel::execute('systemnotificationrecipient', $notificationRecipientData);
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in addRecipient: ' . $e->getMessage());
        }
    }

    /**
     * Creates notification recipients for multiple users,
     * excluding the current user.
     *
     * @param  object $notification The notification object
     * @param  array  $userIds      Array of user IDs to notify
     * @return void
     * @throws \Gaia\Exception\Exception If an error occurs during recipient creation
     */
    public function notifyUsers($notification, $userIds)
    {
        global $currentUser;
        try {
            foreach ($userIds as $userId) {
                if ($currentUser->id !== $userId) {
                    $this->addRecipient($notification->id, $userId);
                }
            }
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in notifyUsers: ' . $e->getMessage());
        }
    }
}
