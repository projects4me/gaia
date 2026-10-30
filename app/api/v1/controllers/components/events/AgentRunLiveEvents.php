<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\Libraries\Workflows\AgentRunRequests;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;

/**
 * Publishes workflow.agent.run.requested to the agent's own user scope
 * (user:<agentUserId>), so only the agent's runtime receives it. The event
 * carries ids only, never the prompt (hermes D-017): resource is the run,
 * meta the workflow, the step and the record the run is about. The runtime
 * reads the step from GET /api/v1/workflowactorstep/{stepId}.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components\Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentRunLiveEvents implements AgentRunRequests
{
    /**
     * @var HermesPublisher
     */
    protected $publisher;

    /**
     * @param HermesPublisher|null $publisher
     */
    public function __construct($publisher = null)
    {
        $this->publisher = $publisher ?: new HermesPublisher();
    }

    /**
     * {@inheritdoc}
     */
    public function requested(array $step)
    {
        if (empty($step['actorUserId']) || empty($step['runId']) || empty($step['id'])) {
            return;
        }

        $this->publisher->publishDomainEvent(
            EventNames::WORKFLOW_AGENT_RUN_REQUESTED,
            'user:' . (string) $step['actorUserId'],
            'workflowRun',
            (string) $step['runId'],
            array(),
            array(
                'workflowId' => (string) $step['workflowId'],
                'stepId' => (string) $step['id'],
                'entityType' => isset($step['entityType']) ? $step['entityType'] : null,
                'entityId' => isset($step['entityId']) ? $step['entityId'] : null,
            )
        );
    }
}
