<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workflows\WorkflowGraph;
use Gaia\Libraries\Workflows\WorkflowGraphReader;
use Gaia\Libraries\Workflows\WorkflowStatus;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks a workflow edge as it is saved: its workflow exists
 * and is not active, `workflowId` never changes, and the edge fits the
 * workflow's graph (see `WorkflowGraph::edgeErrors`: both nodes in the
 * workflow, a free port of the source node, not into the trigger, no loop).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workflowEdgeBehavior extends Behavior implements BehaviorInterface
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
        return $this->checkEditable($model) && $this->checkEdge($model);
    }

    /**
     * A delete only needs the workflow to be editable.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (is_array($snapshot) && isset($snapshot['workflowId'])
            && (string) $snapshot['workflowId'] !== (string) $model->workflowId
        ) {
            return $this->reject($model, 'workflowId', 'The workflow of an edge cannot be changed.');
        }
        if (!empty($model->isDeleting)) {
            return $this->checkEditable($model);
        }

        return $this->checkEditable($model) && $this->checkEdge($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkEditable($model)
    {
        $error = WorkflowStatus::graphEditError($this->workflowStatus((string) $model->workflowId));
        return $error === null || $this->reject($model, 'workflowId', $error);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkEdge($model)
    {
        $workflowId = (string) $model->workflowId;
        $edge = [
            'id' => (string) $model->id,
            'fromNodeId' => (string) $model->fromNodeId,
            'fromPort' => (string) $model->fromPort,
            'toNodeId' => (string) $model->toNodeId,
        ];
        $others = array_filter($this->workflowEdges($workflowId), function ($other) use ($edge) {
            return $other['id'] !== $edge['id'];
        });

        $errors = (new WorkflowGraph())->edgeErrors($this->workflowNodes($workflowId), array_values($others), $edge);
        foreach ($errors as $error) {
            $model->appendMessage(new Message($error, 'fromPort', 'InvalidValue'));
        }

        return $errors === [];
    }

    /**
     * @param  string $workflowId
     * @return string|null
     */
    protected function workflowStatus($workflowId)
    {
        return (new WorkflowGraphReader())->status($workflowId);
    }

    /**
     * @param  string $workflowId
     * @return array
     */
    protected function workflowNodes($workflowId)
    {
        return (new WorkflowGraphReader())->nodes($workflowId);
    }

    /**
     * @param  string $workflowId
     * @return array
     */
    protected function workflowEdges($workflowId)
    {
        return (new WorkflowGraphReader())->edges($workflowId);
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
