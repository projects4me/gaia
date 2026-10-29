<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workflows\NodeConfigRules;
use Gaia\Libraries\Workflows\NodeKindCatalog;
use Gaia\Libraries\Workflows\WorkflowGraph;
use Gaia\Libraries\Workflows\WorkflowGraphReader;
use Gaia\Libraries\Workflows\WorkflowStatus;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks a workflow node as it is saved: its workflow exists
 * and is not active, `workflowId` never changes, the kind belongs to the
 * category, the config (a JSON object; an object sent as JSON is stored as
 * its JSON text) is valid for the kind, and a workflow has at most one
 * trigger. Moving or renaming a node of an active workflow is allowed, since
 * it leaves the graph as it was checked.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workflowNodeBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Fields that shape the graph; the others (name, position) only change
     * how the node is drawn.
     *
     * @var array
     */
    public const GRAPH_FIELDS = ['workflowId', 'category', 'kind', 'config'];

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
        return $this->checkEditable($model) && $this->checkNode($model);
    }

    /**
     * A delete only needs the workflow to be editable; a layout change not
     * even that.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        $snapshot = is_array($snapshot) ? $snapshot : [];

        if (isset($snapshot['workflowId']) && (string) $snapshot['workflowId'] !== (string) $model->workflowId) {
            return $this->reject($model, 'workflowId', 'The workflow of a node cannot be changed.');
        }
        if (!empty($model->isDeleting)) {
            return $this->checkEditable($model);
        }
        if (!$this->graphChanged($model, $snapshot)) {
            return $this->normalizeConfig($model);
        }

        return $this->checkEditable($model) && $this->checkNode($model);
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
    protected function checkNode($model)
    {
        if (!$this->normalizeConfig($model)) {
            return false;
        }
        $config = WorkflowGraphReader::decodeConfig($model->readAttribute('config'));

        $category = (string) $model->category;
        $kind = (string) $model->kind;
        $catalog = new NodeKindCatalog();
        if (!in_array($category, $catalog->categories(), true)) {
            return $this->reject($model, 'category', 'category must be one of ' . implode(', ', $catalog->categories()) . '.');
        }
        if (!$catalog->has($category, $kind)) {
            return $this->reject($model, 'kind', "{$kind} is not a kind of {$category} node.");
        }

        $errors = $this->configRules()->errors($category, $kind, $config);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $model->appendMessage(new Message($error, 'config', 'InvalidValue'));
            }
            return false;
        }

        return $category !== NodeKindCatalog::TRIGGER || $this->checkSingleTrigger($model);
    }

    /**
     * Store the config as JSON object text; reject anything else.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function normalizeConfig($model)
    {
        $config = WorkflowGraphReader::decodeConfig($model->readAttribute('config'));
        if ($config === null) {
            return $this->reject($model, 'config', 'config must be a JSON object.');
        }
        $model->writeAttribute('config', json_encode((object) $config));

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkSingleTrigger($model)
    {
        $others = array_filter($this->workflowNodes((string) $model->workflowId), function ($node) use ($model) {
            return $node['id'] !== (string) $model->id;
        });

        return (new WorkflowGraph())->triggerCount($others) === 0
            || $this->reject($model, 'category', 'A workflow can have only one trigger.');
    }

    /**
     * Whether the save changes anything but the node's layout.
     *
     * @param  ModelInterface $model
     * @param  array          $snapshot
     * @return bool
     */
    protected function graphChanged($model, array $snapshot)
    {
        foreach (self::GRAPH_FIELDS as $field) {
            $current = $model->readAttribute($field);
            $stored = isset($snapshot[$field]) ? $snapshot[$field] : null;
            if ($field === 'config') {
                $current = WorkflowGraphReader::decodeConfig($current);
                $stored = WorkflowGraphReader::decodeConfig($stored);
            }
            if ($current != $stored) {
                return true;
            }
        }

        return false;
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
     * @return NodeConfigRules
     */
    protected function configRules()
    {
        return new NodeConfigRules();
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
