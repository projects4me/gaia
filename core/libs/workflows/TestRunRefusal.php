<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * A test run that is refused. The code is the HTTP status that fits: 404 (no such
 * workflow, trigger or record) or 422 (the request itself is wrong).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TestRunRefusal extends \RuntimeException
{
}
