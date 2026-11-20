<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

/**
 * A member removal that is refused. The code is the HTTP status that fits: 409
 * (open issues need a handover) or 422 (the handover target is wrong).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MemberRemovalRefusal extends \RuntimeException
{
}
