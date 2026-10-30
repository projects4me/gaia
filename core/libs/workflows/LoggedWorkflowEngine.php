<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The engine until workflows can run (agaia-4051): it writes each run it
 * is asked to start to the application log and runs nothing.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LoggedWorkflowEngine implements WorkflowEngine
{
    /**
     * {@inheritdoc}
     */
    public function start($workflowId, $triggerNodeId, array $payload)
    {
        global $logger;

        if ($logger) {
            $logger->info(sprintf(
                'Workflow %s triggered by %s (event %s, node %s); not run: no engine yet.',
                $workflowId,
                isset($payload['eventName']) ? $payload['eventName'] : '?',
                isset($payload['eventId']) ? $payload['eventId'] : '?',
                $triggerNodeId
            ));
        }
    }
}
