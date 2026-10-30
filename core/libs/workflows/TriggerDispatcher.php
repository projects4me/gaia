<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Hands a domain event to the workflows it starts: the Active workflows
 * with a trigger node of the event's kind whose scope covers the event's
 * project.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TriggerDispatcher
{
    /**
     * @var ActiveTriggerNodes
     */
    private $nodes;

    /**
     * @var WorkflowEngine
     */
    private $engine;

    /**
     * @param ActiveTriggerNodes|null $nodes
     * @param WorkflowEngine|null     $engine
     */
    public function __construct(ActiveTriggerNodes $nodes = null, WorkflowEngine $engine = null)
    {
        $this->nodes = $nodes ?: new ActiveTriggerNodes();
        $this->engine = $engine ?: new LoggedWorkflowEngine();
    }

    /**
     * Starts each workflow the event triggers.
     *
     * @param  array $payload The trigger payload (see TriggerPayload).
     * @return int The number of runs started.
     */
    public function dispatch(array $payload)
    {
        $projectId = isset($payload['projectId']) ? $payload['projectId'] : null;
        $started = 0;
        foreach ($this->nodes->ofKind($payload['eventName']) as $node) {
            if (!WorkflowScope::covers($node['scope'], $projectId)) {
                continue;
            }
            $this->engine->start($node['workflowId'], $node['nodeId'], $payload);
            $started++;
        }

        return $started;
    }
}
