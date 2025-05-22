<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Modules;

use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Project;
use Gaia\Workflows\Actions\CreateModel;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * Handles notifications for time-tracking related events including
 * creation, updates, and deletion of time logs for issues.
 * Supports both estimated and spent time notifications.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Modules
 * @category Notification
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TimelogNotification implements NotificationModuleInterface
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
     * Creates a notification when a timelog is created and notifies relevant issue stakeholders.
     * Handles both estimated time and spent time log entries differently.
     *
     * @param  object $model The timelog model that was created
     * @return object|null The created notification or null if issue not found
     * @throws \Gaia\Exception\Exception If an error occurs during notification creation
     */
    public function onCreate($model)
    {
        global $logger, $currentUser;
        try {
            $logger->debug('TimelogNotification::onCreate()');

            $timelogContext = [
                'est' => 'estimated',
                'spent' => 'logged'
            ];

            $issue = Issue::findFirstById($model->issueId);
            if (!$issue) {
                $logger->debug('Issue not found for timelog: ' . $model->id);
                return null;
            }

            $project = Project::findFirstById($issue->projectId);

            // Prepare common context data
            $contextData = [
                "projectShortcode" => $project->shortCode,
                "issueNumber" => $issue->issueNumber,
                "userId" => $currentUser->id,
                "userName" => $currentUser->name,
                'relatedTo' => 'timelog',
                'timelogId' => $model->id,
                'timelogType' => $timelogContext[$model->context]
            ];

            // Add spent-specific field if applicable
            if ($model->context == 'spent') {
                $contextData['spentOn'] = $model->spentOn;
            }

            // Set description based on context
            $description = ($model->context == 'est')
                ? "{{User@{$currentUser->id}}} has {{Timelog@{$model->id}}} time for {{Issue@{$issue->id}}}"
                : "{{User@{$currentUser->id}}} has {{Timelog@{$model->id}}} time on {{Issue@{$issue->id}}}";

            $notificationData = [
                'description' => $description,
                'context' => json_encode($contextData)
            ];

            $notification = CreateModel::execute('systemnotification', $notificationData);
            $this->recipientService->notifyIssueRecipients($notification, $issue);

            $logger->debug('-TimelogNotification::onCreate()');
            return $notification;
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in TimelogNotification::onCreate: ' . $e->getMessage());
        }
    }

    /**
     * Currently not implemented - placeholder for future functionality.
     *
     * @param  object $model The timelog model that was updated
     * @return null No notification is currently created for timelog updates
     */
    public function onUpdate($model)
    {
        // Timelog update notifications not currently implemented
        return null;
    }

    /**
     * Currently not implemented - placeholder for future functionality.
     *
     * @param  object $model The timelog model that was deleted
     * @return null No notification is currently created for timelog deletion
     */
    public function onDelete($model)
    {
        // Timelog delete notifications not currently implemented
        return null;
    }
}
