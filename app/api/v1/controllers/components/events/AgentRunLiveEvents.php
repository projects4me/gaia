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
 * A delegable agent's request also goes to the `runtimes` scope (D-012), so
 * any runtime can take it; the step's claim decides which one does.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components\Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentRunLiveEvents implements AgentRunRequests
{
    /**
     * The scope every runtime listens on (hermes: runtime service tokens only).
     */
    const RUNTIMES_SCOPE = 'runtimes';

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

        $this->publish('user:' . (string) $step['actorUserId'], $step);
        if (!empty($step['delegable'])) {
            $this->publish(self::RUNTIMES_SCOPE, $step);
        }
    }

    /**
     * @param  string $scope
     * @param  array  $step
     * @return void
     */
    private function publish($scope, array $step)
    {
        $this->publisher->publishDomainEvent(
            EventNames::WORKFLOW_AGENT_RUN_REQUESTED,
            $scope,
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
