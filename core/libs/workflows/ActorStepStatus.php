<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The states of an actor step (workflow_actor_steps). A 'Run agent' step is
 * pending until the agent's runtime claims it, claimed until it reports,
 * then finished; one not claimed or not finished in time is timed out. An
 * 'Ask a user' step is pending until the user replies or is replaced.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActorStepStatus
{
    public const PENDING = 'pending';
    public const CLAIMED = 'claimed';
    public const FINISHED = 'finished';
    public const TIMED_OUT = 'timed_out';
}
