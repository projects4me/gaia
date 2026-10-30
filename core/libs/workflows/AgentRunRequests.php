<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Tells an agent's runtime that a 'Run agent' step waits for it. The
 * request carries ids only; the runtime reads the step from Gaia.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface AgentRunRequests
{
    /**
     * @param  array $step The actor step: id, runId, workflowId, actorUserId, entityType, entityId.
     * @return void
     */
    public function requested(array $step);
}
