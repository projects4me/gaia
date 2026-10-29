<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workflows\LiveRecords;
use Gaia\Libraries\Workflows\WorkflowGraph;
use Gaia\Libraries\Workflows\WorkflowGraphReader;
use Gaia\Libraries\Workflows\WorkflowStatus;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks a workflow as it is saved: `status` is Draft, Active
 * or Disabled, `scope` is a JSON list of existing project ids (empty: every
 * project; a list sent as JSON is stored as its JSON text) and `ownerId`
 * names an existing user. Activating a workflow checks its whole graph (see
 * `WorkflowGraph::errors`) and counts a new `version`, so each activated
 * graph has its own number; the version cannot be written directly.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workflowBehavior extends Behavior implements BehaviorInterface
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
     * A new workflow has no graph yet, so it cannot start out active.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        $status = $model->readAttribute('status');
        if ($status === null || $status === '') {
            $model->writeAttribute('status', WorkflowStatus::DRAFT);
        }
        $model->writeAttribute('version', 0);

        if (!$this->checkFields($model)) {
            return false;
        }

        return $model->readAttribute('status') !== WorkflowStatus::ACTIVE
            || $this->reject($model, 'status', 'Add a trigger and its steps before activating a workflow.');
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $storedVersion = isset($snapshot['version']) ? (int) $snapshot['version'] : 0;
        $model->writeAttribute('version', $storedVersion);

        if (!empty($model->isDeleting)) {
            return true;
        }
        if (!$this->checkFields($model)) {
            return false;
        }

        $storedStatus = isset($snapshot['status']) ? (string) $snapshot['status'] : null;
        if ($model->readAttribute('status') !== WorkflowStatus::ACTIVE || $storedStatus === WorkflowStatus::ACTIVE) {
            return true;
        }
        if (!$this->checkGraph($model)) {
            return false;
        }
        $model->writeAttribute('version', $storedVersion + 1);

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkFields($model)
    {
        if (!in_array($model->readAttribute('status'), WorkflowStatus::ALL, true)) {
            return $this->reject($model, 'status', 'status must be one of ' . implode(', ', WorkflowStatus::ALL) . '.');
        }

        return $this->checkScope($model) && $this->checkOwner($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkScope($model)
    {
        $scope = $model->readAttribute('scope');
        if ($scope === null || $scope === '') {
            $scope = [];
        } elseif (!is_array($scope)) {
            $scope = json_decode((string) $scope, true);
        }

        $isList = is_array($scope) && ($scope === [] || array_keys($scope) === range(0, count($scope) - 1));
        if (!$isList) {
            return $this->reject($model, 'scope', 'scope must be a JSON list of project ids.');
        }
        foreach ($scope as $projectId) {
            if (!is_string($projectId) || !$this->exists('Project', $projectId)) {
                return $this->reject($model, 'scope', 'scope must list existing projects.');
            }
        }

        $model->writeAttribute('scope', json_encode(array_values(array_unique($scope))));
        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkOwner($model)
    {
        $ownerId = $model->readAttribute('ownerId');
        if ($ownerId === null || $ownerId === '' || $this->exists('User', (string) $ownerId)) {
            return true;
        }

        return $this->reject($model, 'ownerId', 'ownerId must be an existing user.');
    }

    /**
     * Report every problem that keeps the graph from running.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkGraph($model)
    {
        list($nodes, $edges) = $this->graph((string) $model->id);
        $errors = (new WorkflowGraph())->errors($nodes, $edges);
        foreach ($errors as $error) {
            $model->appendMessage(new Message($error, 'status', 'InvalidValue'));
        }

        return $errors === [];
    }

    /**
     * The workflow's stored nodes and edges.
     *
     * @param  string $workflowId
     * @return array [nodes, edges]
     */
    protected function graph($workflowId)
    {
        $reader = new WorkflowGraphReader();
        return [$reader->nodes($workflowId), $reader->edges($workflowId)];
    }

    /**
     * Whether a live (not deleted) row of the model has this id.
     *
     * @param  string $model
     * @param  string $id
     * @return bool
     */
    protected function exists($model, $id)
    {
        return (new LiveRecords())->exists($model, $id);
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
