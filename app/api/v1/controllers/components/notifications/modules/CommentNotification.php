<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Modules;

use Gaia\MVC\Models\Conversationroom;
use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Project;
use Gaia\Workflows\Actions\CreateModel;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * Handles notifications for comment-related events including creation,
 * updates, and deletion of comments in conversation rooms. Currently focused
 * on comment notifications for issues.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Modules
 * @category Notification
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CommentNotification implements NotificationModuleInterface
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
     * Creates a notification when a comment is added to an issue's conversation room
     * and notifies relevant issue stakeholders.
     *
     * @param object $model The comment model that was created
     * @return object|null The created notification or null if not related to an issue
     * @throws \Gaia\Exception\Exception If an error occurs during notification creation
     */
    public function onCreate($model)
    {
        global $currentUser, $logger;
        try {
            $logger->debug('CommentNotification::onCreate()');

            if ($model->relatedTo != 'conversationrooms') {
                return null;
            }

            $conversationroom = Conversationroom::findFirstById($model->relatedId);
            if (!$conversationroom->issueId) {
                return null;
            }

            $issue = Issue::findFirstById($conversationroom->issueId);
            $project = Project::findFirstById($issue->projectId);

            $notificationData = [
                'description' => "{{User@{$currentUser->id}}} has {{Comment@{$model->id}}} on {{Issue@{$conversationroom->issueId}}}",
                'context' => json_encode(
                    [
                    "projectShortcode" => $project->shortCode,
                    "issueNumber" => $issue->issueNumber,
                    "userId" => $currentUser->id,
                    "userName" => $currentUser->name,
                    'relatedTo' => 'comment',
                    'commentId' => $model->id
                    ]
                )
            ];

            $notification = CreateModel::execute('systemnotification', $notificationData);
            $this->recipientService->notifyIssueRecipients($notification, $issue);

            $logger->debug('-CommentNotification::onCreate()');
            return $notification;
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in CommentNotification::onCreate: ' . $e->getMessage());
        }
    }

    /**
     * Currently not implemented - placeholder for future functionality.
     *
     * @param object $model The comment model that was updated
     * @return null No notification is currently created for comment updates
     */
    public function onUpdate($model)
    {
        // Comment update notifications not currently implemented
        return null;
    }

    /**
     * Currently not implemented - placeholder for future functionality.
     *
     * @param object $model The comment model that was deleted
     * @return null No notification is currently created for comment deletion
     */
    public function onDelete($model)
    {
        // Comment delete notifications not currently implemented
        return null;
    }
}
