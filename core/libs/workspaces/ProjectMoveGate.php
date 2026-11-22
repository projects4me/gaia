<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\Exception\Access;
use Gaia\Exception\Conflict;
use Gaia\Libraries\Workflows\RunStatus;
use Gaia\Libraries\Workflows\WorkflowScope;
use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Workflow;
use Gaia\MVC\Models\Workflowrun;
use Gaia\MVC\Models\Workspace;
use Phalcon\Di\Di;

/**
 * Decides whether a project may move to another workspace (Workspaces D-011):
 * the target must exist, the caller needs `workspace.update`, and nothing of
 * the old workspace may still be working in the project, and the project's
 * repositories, which move with it (D-015), must not clash by name with the
 * target's. Each thing that blocks a move is its own check, listed in
 * CHECKS, so a new kind of owned thing is one more method.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectMoveGate
{
    const TARGET_REQUIRED = 'A project must belong to a workspace.';

    const NOT_ALLOWED = 'You are not allowed to move projects between these workspaces.';

    /**
     * The checks for work in progress and repository name clashes, run in
     * order; the first that finds something refuses the move.
     */
    const CHECKS = ['checkWorkflowRuns', 'checkAgentIssues', 'checkScopedWorkflows', 'checkRepositoryNames'];

    /**
     * @var WorkspaceMemberships
     */
    protected $memberships;

    /**
     * @var ProjectRepositories
     */
    protected $repositories;

    /**
     * @param WorkspaceMemberships|null $memberships
     * @param ProjectRepositories|null  $repositories
     */
    public function __construct($memberships = null, $repositories = null)
    {
        $this->memberships = $memberships ?: new WorkspaceMemberships();
        $this->repositories = $repositories ?: new ProjectRepositories();
    }

    /**
     * Refuse the move unless it is allowed.
     *
     * @param  string      $projectId
     * @param  string|null $fromWorkspaceId The stored workspace, null for a legacy project
     * @param  mixed       $toWorkspaceId   The workspace being saved
     * @return void
     * @throws \InvalidArgumentException No or an unknown target workspace (422)
     * @throws Access                    The caller may not update workspaces (403)
     * @throws Conflict                  Work of the old workspace is still in progress (409)
     */
    public function check($projectId, $fromWorkspaceId, $toWorkspaceId)
    {
        $this->checkTarget($toWorkspaceId);
        $this->checkPermission();

        foreach (self::CHECKS as $check) {
            $this->$check(
                (string) $projectId,
                $this->isBlank($fromWorkspaceId) ? null : (string) $fromWorkspaceId,
                (string) $toWorkspaceId
            );
        }
    }

    /**
     * @param  mixed $toWorkspaceId
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function checkTarget($toWorkspaceId)
    {
        if ($this->isBlank($toWorkspaceId)) {
            throw new \InvalidArgumentException(self::TARGET_REQUIRED);
        }

        $workspace = Workspace::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $toWorkspaceId],
        ]);
        if (!$workspace) {
            throw new \InvalidArgumentException(ProjectWorkspace::UNKNOWN_WORKSPACE);
        }
    }

    /**
     * `workspace.update` has no record scope, so one check covers both the
     * old and the new workspace. Outside a request (no ACL loaded) it is
     * skipped.
     *
     * @return void
     * @throws Access
     */
    protected function checkPermission()
    {
        $di = Di::getDefault();
        if ($di === null || !$di->has('acl')) {
            return;
        }
        if (!$di->get('acl')->isModelActionAllowed('Workspace', 'update')) {
            throw new Access(self::NOT_ALLOWED);
        }
    }

    /**
     * Runs of the project that are still running or waiting.
     *
     * @param  string      $projectId
     * @param  string|null $fromWorkspaceId
     * @return void
     * @throws Conflict
     */
    protected function checkWorkflowRuns($projectId, $fromWorkspaceId)
    {
        $count = Workflowrun::count([
            'conditions' => 'projectId = :projectId: AND deleted = false AND status IN ({statuses:array})',
            'bind' => ['projectId' => $projectId, 'statuses' => [RunStatus::RUNNING, RunStatus::WAITING]],
        ]);
        if ($count > 0) {
            throw new Conflict(
                'The project has ' . $count . ' running or waiting workflow run(s); '
                . 'wait for them to finish before moving it.'
            );
        }
    }

    /**
     * Open issues of the project assigned to agents of the old workspace.
     *
     * @param  string      $projectId
     * @param  string|null $fromWorkspaceId
     * @return void
     * @throws Conflict
     */
    protected function checkAgentIssues($projectId, $fromWorkspaceId)
    {
        if ($fromWorkspaceId === null) {
            return;
        }
        $agentUserIds = $this->memberships->agentUserIds($fromWorkspaceId);
        if (empty($agentUserIds)) {
            return;
        }

        $count = Issue::count([
            'conditions' => 'projectId = :projectId: AND assignee IN ({agentUserIds:array}) AND deleted = 0'
                . ' AND statusId IN (SELECT s.id FROM Gaia\MVC\Models\Issuestatus s WHERE s.done = 0)',
            'bind' => ['projectId' => $projectId, 'agentUserIds' => $agentUserIds],
        ]);
        if ($count > 0) {
            throw new Conflict(
                'The project has ' . $count . ' open issue(s) assigned to agents of its workspace; '
                . 'hand them over before moving it.'
            );
        }
    }

    /**
     * Workflows of the old workspace whose scope lists the project.
     *
     * @param  string      $projectId
     * @param  string|null $fromWorkspaceId
     * @return void
     * @throws Conflict
     */
    protected function checkScopedWorkflows($projectId, $fromWorkspaceId)
    {
        if ($fromWorkspaceId === null) {
            return;
        }

        $workflows = Workflow::find([
            'conditions' => 'workspaceId = :workspaceId: AND deleted = false',
            'bind' => ['workspaceId' => $fromWorkspaceId],
            'columns' => 'name, scope',
            'order' => 'name',
        ]);

        $names = [];
        foreach ($workflows as $workflow) {
            if (WorkflowScope::covers(WorkflowScope::decode($workflow->scope), $projectId)) {
                $names[] = (string) $workflow->name;
            }
        }
        if (!empty($names)) {
            throw new Conflict(
                'Workflows of the project\'s workspace list it in their scope: ' . implode(', ', $names)
                . '; remove it from their scope before moving it.'
            );
        }
    }

    /**
     * The project's repositories move with it (D-015), so none may share a
     * name with a repository of the target workspace.
     *
     * @param  string      $projectId
     * @param  string|null $fromWorkspaceId
     * @param  string      $toWorkspaceId
     * @return void
     * @throws Conflict
     */
    protected function checkRepositoryNames($projectId, $fromWorkspaceId, $toWorkspaceId)
    {
        $names = $this->repositories->clashingNames($projectId, $toWorkspaceId);
        if (!empty($names)) {
            throw new Conflict(
                'The target workspace already has repositories named ' . implode(', ', $names)
                . '; rename the project\'s repositories before moving it.'
            );
        }
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isBlank($value)
    {
        return $value === null || (string) $value === '';
    }
}
