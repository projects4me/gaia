<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Repository;

/**
 * The repositories tied to one project (Workspaces D-015): they move with the
 * project to its new workspace, so a project cannot move into a workspace
 * that already has a repository of the same name.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectRepositories
{
    /**
     * Names of the project's repositories that a live repository of the
     * target workspace already uses.
     *
     * @param  string $projectId
     * @param  string $toWorkspaceId
     * @return string[] sorted
     */
    public function clashingNames($projectId, $toWorkspaceId)
    {
        $rows = Repository::find([
            'conditions' => 'projectId = :projectId: AND deleted = false AND name IN ('
                . 'SELECT Taken.name FROM Gaia\MVC\Models\Repository Taken'
                . ' WHERE Taken.workspaceId = :workspaceId: AND Taken.deleted = false)',
            'bind' => ['projectId' => (string) $projectId, 'workspaceId' => (string) $toWorkspaceId],
            'columns' => 'name',
            'order' => 'name',
        ]);

        $names = [];
        foreach ($rows as $row) {
            $names[] = (string) $row->name;
        }

        return $names;
    }

    /**
     * Move the project's live repositories to its new workspace. Runs inside
     * the project's move transaction; a repository that fails to save throws,
     * so the whole move rolls back.
     *
     * @param  string $projectId
     * @param  string $toWorkspaceId
     * @return int the number of repositories moved
     * @throws \RuntimeException
     */
    public function move($projectId, $toWorkspaceId)
    {
        $repositories = Repository::find([
            'conditions' => 'projectId = :projectId: AND deleted = false AND workspaceId <> :workspaceId:',
            'bind' => ['projectId' => (string) $projectId, 'workspaceId' => (string) $toWorkspaceId],
        ]);

        $moved = 0;
        foreach ($repositories as $repository) {
            $repository->workspaceId = (string) $toWorkspaceId;
            $repository->moveWithProject();
            if (!$repository->save()) {
                $messages = $repository->getMessages();
                $reason = empty($messages) ? 'unknown error' : (string) $messages[0]->getMessage();
                throw new \RuntimeException(
                    'Repository ' . $repository->name . ' could not move with the project: ' . $reason
                );
            }
            $moved++;
        }

        return $moved;
    }
}
