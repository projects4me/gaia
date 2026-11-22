<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\RepositoryUrl;
use Gaia\MVC\Models\Project;
use Gaia\MVC\Models\Repository;
use Gaia\MVC\Models\Workspace;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks a workspace repository as it is saved: its workspace
 * exists and never changes, the URL is an https or ssh git URL without
 * credentials (see `RepositoryUrl`), the base branch is set, and the name is
 * unique within the workspace. A projectId, when set or changed, must be a
 * project of the same workspace (Workspaces D-015). Only a project's move
 * (`ProjectRepositories`) changes a repository's workspace.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class repositoryBehavior extends Behavior implements BehaviorInterface
{
    const PROJECT_ERROR = 'projectId must be the id of a project of the repository\'s workspace';

    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        $workspaceId = (string) $model->readAttribute('workspaceId');
        if ($workspaceId === '' || !$this->workspaceExists($workspaceId)) {
            return $this->reject($model, 'workspaceId', 'workspaceId must be the id of an existing workspace');
        }
        if (!$this->checkProject($model)) {
            return false;
        }

        return $this->checkRepository($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (is_array($snapshot) && isset($snapshot['workspaceId'])
            && (string) $snapshot['workspaceId'] !== (string) $model->readAttribute('workspaceId')
            && !(method_exists($model, 'isMovingWithProject') && $model->isMovingWithProject())
        ) {
            return $this->reject($model, 'workspaceId', 'The workspace of a repository cannot be changed');
        }
        if (!empty($model->isDeleting)) {
            return true;
        }

        $projectChanged = !is_array($snapshot)
            || (string) ($snapshot['projectId'] ?? '') !== (string) $model->readAttribute('projectId');
        if ($projectChanged && !$this->checkProject($model)) {
            return false;
        }

        return $this->checkRepository($model);
    }

    /**
     * An empty projectId makes the repository workspace-wide (stored as null);
     * otherwise it must be a live project of the repository's workspace.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkProject($model)
    {
        $projectId = $model->readAttribute('projectId');
        if ($projectId === null || trim((string) $projectId) === '') {
            $model->writeAttribute('projectId', null);
            return true;
        }

        $workspaceId = $this->projectWorkspaceId((string) $projectId);
        if ($workspaceId === null || $workspaceId !== (string) $model->readAttribute('workspaceId')) {
            return $this->reject($model, 'projectId', self::PROJECT_ERROR);
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkRepository($model)
    {
        $error = (new RepositoryUrl())->error($model->readAttribute('url'));
        if ($error !== null) {
            return $this->reject($model, 'url', $error);
        }

        $baseBranch = $model->readAttribute('baseBranch');
        if ($baseBranch === null || trim((string) $baseBranch) === '') {
            $model->writeAttribute('baseBranch', 'main');
        }

        $name = (string) $model->readAttribute('name');
        $workspaceId = (string) $model->readAttribute('workspaceId');
        if ($this->nameTaken($workspaceId, $name, (string) $model->readAttribute('id'))) {
            return $this->reject($model, 'name', 'A repository named ' . $name . ' already exists in this workspace');
        }

        return true;
    }

    /**
     * @param  string $workspaceId
     * @return bool
     */
    protected function workspaceExists($workspaceId)
    {
        return (bool) Workspace::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => $workspaceId],
        ]);
    }

    /**
     * @param  string $projectId
     * @return string|null the live project's workspace, null when there is no such project
     */
    protected function projectWorkspaceId($projectId)
    {
        $project = Project::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => $projectId],
            'columns' => 'workspaceId',
        ]);

        return $project ? (string) $project->workspaceId : null;
    }

    /**
     * @param  string $workspaceId
     * @param  string $name
     * @param  string $exceptId the repository being saved, empty on create
     * @return bool
     */
    protected function nameTaken($workspaceId, $name, $exceptId)
    {
        return (bool) Repository::findFirst([
            'conditions' => 'workspaceId = :workspaceId: AND name = :name: AND deleted = false AND id <> :id:',
            'bind' => ['workspaceId' => $workspaceId, 'name' => $name, 'id' => $exceptId],
        ]);
    }

    /**
     * Add a validation message and cancel the save.
     *
     * @param  ModelInterface $model
     * @param  string         $field
     * @param  string         $message
     * @return bool
     */
    private function reject($model, $field, $message)
    {
        $model->appendMessage(new Message($message, $field, 'InvalidValue'));
        return false;
    }
}
