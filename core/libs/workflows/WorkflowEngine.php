<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Runs a workflow from its trigger node for one event. Triggers hand matched
 * workflows over through this boundary, so they don't depend on how a run
 * is carried out.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface WorkflowEngine
{
    /**
     * Starts a run of the workflow from the trigger node.
     *
     * @param  string $workflowId
     * @param  string $triggerNodeId
     * @param  array  $payload The trigger payload (see TriggerPayload).
     * @return void
     */
    public function start($workflowId, $triggerNodeId, array $payload);
}
