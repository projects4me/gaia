<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\Libraries\Workflows\WorkflowRunEvents;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;

/**
 * Publishes workflow.run.finished to the run's project when a run completes
 * or fails. Ids only: resource is the run, meta the workflow, the status,
 * the test flag and the record the run is about; never the error text.
 * A run without a project publishes nothing.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components\Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowRunLiveEvents implements WorkflowRunEvents
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
    public function finished(array $run)
    {
        if (empty($run['projectId']) || empty($run['runId'])) {
            return;
        }

        $this->publisher->publishDomainEvent(
            EventNames::WORKFLOW_RUN_FINISHED,
            (string) $run['projectId'],
            'workflowRun',
            (string) $run['runId'],
            array(),
            array(
                'workflowId' => (string) $run['workflowId'],
                'status' => (string) $run['status'],
                'test' => !empty($run['test']),
                'entityType' => isset($run['entityType']) ? $run['entityType'] : null,
                'entityId' => isset($run['entityId']) ? $run['entityId'] : null,
            )
        );
    }
}
