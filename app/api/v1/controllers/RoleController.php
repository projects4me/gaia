<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Roles Controller
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RoleController extends RestController
{
    /**
     * Project authorization flag
     * @var bool
     */
    protected $projectAuthorization = false;

    /**
     * System level flag
     * @var bool
     */
    protected $systemLevel = true;
}
