<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * An update to an actor step that is refused. The code is the HTTP status
 * that fits: 403 (not the step's actor), 404 (no such step), 409 (the step
 * is past that state) or 422 (the update itself is wrong).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActorStepRefusal extends \RuntimeException
{
}
