<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Workflows\Process;

use Gaia\Workflows\Actions\{GetModel, CreateModel};
use Gaia\MVC\Models\User;

/**
 * MilestoneActivityLog class handles logging activities for overdue milestones
 *
 * This process provides functionality to automatically log activities when project milestones
 * become overdue. It checks for milestones that have passed their end date but are still
 * marked as in progress, and creates corresponding activity log entries.
 *
 * @package Gaia\Workflows\Actions
 * @author  Rana Nouman <ranamnouman@gmail.com>
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MilestoneActivityLog
{
    /**
     * This function retrieves all milestones that are past their end date and are still in progress.
     * For each overdue milestone, it creates an activity log entry indicating that the milestone is due today.
     *
     * @return void
     */
    public static function run()
    {
        $currentDate = gmdate('Y-m-d H:i:s');
        $milestones = GetModel::execute('milestone', "((endDate < $currentDate) AND (status : in_progress))");

        // To avoid any behavior related issues, we will set the system user as the current user
        $user = User::getSystemUser();
        User::setCurrentUser($user);
        foreach ($milestones as $milestone) {
            $data = [
                'id' => create_guid(),
                'description' => 'Milestone is due today',
                'projectId' => $milestone->projectId,
                'relatedTo' => 'project',
                'relatedId' => $milestone->projectId,
                'relatedActivityModule' => 'milestone',
                'type' => 'related',
                'relatedActivity' => 'overdue',
                'milestoneId' => $milestone->id,
                'activity' => 'Milestone is due today',
                'createdUser' => 'system',
                'createdUserName' => 'System',
                'dateCreated' => date('Y-m-d H:i:s'),
            ];
            CreateModel::execute('activity', $data);
        }
    }
}
