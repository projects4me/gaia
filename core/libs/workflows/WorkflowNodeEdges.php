<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Workflowedge;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;

/**
 * Removes the edges into and out of a workflow node that is being deleted,
 * so no edge is left pointing at a missing node.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowNodeEdges
{
    /**
     * Delete the node's edges; a failed delete is reported on the node.
     *
     * @param  ModelInterface $node a Workflownode
     * @return bool
     */
    public function removeFor(ModelInterface $node)
    {
        $edges = Workflowedge::find([
            'conditions' => '(fromNodeId = :id: OR toNodeId = :id:) AND deleted = false',
            'bind' => ['id' => (string) $node->id],
        ]);

        foreach ($edges as $edge) {
            if (!$edge->delete()) {
                $messages = $edge->getMessages();
                $node->appendMessage($messages ? $messages[0] : new Message('The node\'s edges could not be removed.'));
                return false;
            }
        }

        return true;
    }
}
