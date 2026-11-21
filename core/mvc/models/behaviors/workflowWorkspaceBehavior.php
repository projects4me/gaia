<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workflows\WorkflowGraphReader;
use Gaia\Libraries\Workflows\WorkflowScope;
use Gaia\Libraries\Workflows\WorkflowStatus;
use Gaia\Libraries\Workspaces\WorkflowWorkspace;
use Gaia\MVC\Models\Workspace;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * A workflow belongs to one existing workspace, given when it is created and
 * never changed; its scope lists only projects of that workspace and, when it
 * is activated, the agents its nodes name are all in it (Workspaces D-008).
 * The rules live in {@see WorkflowWorkspace}.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workflowWorkspaceBehavior extends Behavior implements BehaviorInterface
{
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
        if ($workspaceId === '') {
            return $this->reject($model, 'workspaceId', WorkflowWorkspace::WORKSPACE_REQUIRED);
        }
        if (!$this->workspaceExists($workspaceId)) {
            return $this->reject($model, 'workspaceId', 'workspaceId must be the id of an existing workspace');
        }

        return $this->checkScope($model, $workspaceId);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        if (!empty($model->isDeleting)) {
            return true;
        }
        $snapshot = $model->getSnapshotData();
        $snapshot = is_array($snapshot) ? $snapshot : [];
        if ($this->rule()->isChange($snapshot, $model->readAttribute('workspaceId'))) {
            return $this->reject($model, 'workspaceId', WorkflowWorkspace::WORKSPACE_FIXED);
        }

        $workspaceId = (string) (isset($snapshot['workspaceId']) ? $snapshot['workspaceId'] : $model->readAttribute('workspaceId'));
        if ($workspaceId === '') {
            return true;
        }

        return $this->checkScope($model, $workspaceId) && $this->checkAgentsOnActivation($model, $snapshot, $workspaceId);
    }

    /**
     * @param  ModelInterface $model
     * @param  string         $workspaceId
     * @return bool
     */
    protected function checkScope($model, $workspaceId)
    {
        $foreign = $this->rule()->foreignProjectIds($workspaceId, WorkflowScope::decode($model->readAttribute('scope')));

        return $foreign === []
            || $this->reject($model, 'scope', 'scope lists projects outside the workflow\'s workspace: ' . implode(', ', $foreign) . '.');
    }

    /**
     * Activating a workflow refuses a node that names an agent of another workspace.
     *
     * @param  ModelInterface $model
     * @param  array          $snapshot
     * @param  string         $workspaceId
     * @return bool
     */
    protected function checkAgentsOnActivation($model, array $snapshot, $workspaceId)
    {
        $storedStatus = isset($snapshot['status']) ? (string) $snapshot['status'] : null;
        if ($model->readAttribute('status') !== WorkflowStatus::ACTIVE || $storedStatus === WorkflowStatus::ACTIVE) {
            return true;
        }

        $foreign = $this->rule()->foreignAgentIds($workspaceId, $this->rule()->agentIdsOf($this->nodes((string) $model->id)));

        return $foreign === []
            || $this->reject($model, 'status', 'The workflow runs agents outside its workspace: ' . implode(', ', $foreign) . '.');
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
     * @param  string $workflowId
     * @return array
     */
    protected function nodes($workflowId)
    {
        return (new WorkflowGraphReader())->nodes($workflowId);
    }

    /**
     * @return WorkflowWorkspace
     */
    protected function rule()
    {
        return new WorkflowWorkspace();
    }

    /**
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
