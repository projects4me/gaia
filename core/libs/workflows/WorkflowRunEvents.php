<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Tells the outside that a workflow run has ended. The notice carries ids
 * only; the run's history is read from Gaia.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface WorkflowRunEvents
{
    /**
     * @param  array $run runId, workflowId, projectId, status (RunStatus::COMPLETED or FAILED), test, entityType, entityId.
     * @return void
     */
    public function finished(array $run);
}
