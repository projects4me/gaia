<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Gaia\MVC\REST\Controllers\Components\Common\ValueLabelsComponent;

/**
 * Saves and deletes the translated labels of an issue status with the status
 * (see ValueLabelsComponent). It exists for its name: RestController loads
 * the components a controller `$uses` as {Model}Component.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssuestatusComponent extends ValueLabelsComponent
{
}
