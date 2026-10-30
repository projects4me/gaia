<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Continues a waiting run once its actor step has an answer: the agent's
 * report, the user's reply or a timeout.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface WaitingStepResumer
{
    /**
     * Finishes the waiting step on the port and takes the run on from there.
     *
     * @param  string $stepId
     * @param  string $port
     * @param  array  $outputs What the step produced; later steps read it as {step.*}.
     * @return void
     */
    public function resume($stepId, $port, array $outputs);
}
