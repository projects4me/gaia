<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Workflowreference;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Keeps `workflow_references` in step with the nodes: the users and agents a
 * live node's config names (see `NodeKindCatalog::references`) each have one
 * live row, and a deleted node or workflow has none, so an agent's or a
 * user's workflows can be listed from the references.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowReferenceSync
{
    /**
     * @var NodeKindCatalog
     */
    private $catalog;

    /**
     * @param NodeKindCatalog|null $catalog
     */
    public function __construct(NodeKindCatalog $catalog = null)
    {
        $this->catalog = $catalog ?: new NodeKindCatalog();
    }

    /**
     * Bring the node's references in line with its stored config. A failed
     * write is reported on the node and stops the sync.
     *
     * @param  ModelInterface $node a Workflownode
     * @param  bool           $deleted whether the node is being deleted
     * @return bool
     */
    public function syncNode(ModelInterface $node, $deleted)
    {
        $wanted = [];
        $config = WorkflowGraphReader::decodeConfig($node->config);
        if (!$deleted && is_array($config) && $this->catalog->has($node->category, $node->kind)) {
            foreach ($this->catalog->references($node->category, $node->kind, $config) as $reference) {
                $wanted[$reference['entityType'] . ':' . $reference['entityId']] = $reference;
            }
        }

        foreach ($this->stored('nodeId', $node->id) as $row) {
            $key = $row->entityType . ':' . $row->entityId;
            if (isset($wanted[$key])) {
                unset($wanted[$key]);
            } elseif (!$this->remove($row, $node)) {
                return false;
            }
        }

        foreach ($wanted as $reference) {
            if (!$this->add($node, $reference)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove every reference of a workflow being deleted.
     *
     * @param  ModelInterface $workflow a Workflow
     * @return bool
     */
    public function removeWorkflow(ModelInterface $workflow)
    {
        foreach ($this->stored('workflowId', $workflow->id) as $row) {
            if (!$this->remove($row, $workflow)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string $field
     * @param  string $id
     * @return iterable
     */
    protected function stored($field, $id)
    {
        return Workflowreference::find([
            'conditions' => $field . ' = :id: AND deleted = false',
            'bind' => ['id' => (string) $id],
        ]);
    }

    /**
     * @param  ModelInterface $node
     * @param  array          $reference
     * @return bool
     */
    protected function add(ModelInterface $node, array $reference)
    {
        $row = new Workflowreference();
        $row->id = create_guid();
        $row->workflowId = $node->workflowId;
        $row->nodeId = $node->id;
        $row->entityType = $reference['entityType'];
        $row->entityId = $reference['entityId'];

        return $row->create() || $this->fail($row, $node);
    }

    /**
     * @param  ModelInterface $row
     * @param  ModelInterface $owner
     * @return bool
     */
    protected function remove(ModelInterface $row, ModelInterface $owner)
    {
        return $row->delete() || $this->fail($row, $owner);
    }

    /**
     * Copy a failed write's messages onto the record being saved.
     *
     * @param  ModelInterface $row
     * @param  ModelInterface $owner
     * @return bool
     */
    private function fail(ModelInterface $row, ModelInterface $owner)
    {
        $messages = $row->getMessages();
        if ($messages === []) {
            $owner->appendMessage(new Message('The workflow references could not be updated.'));
        }
        foreach ($messages as $message) {
            $owner->appendMessage($message);
        }

        return false;
    }
}
