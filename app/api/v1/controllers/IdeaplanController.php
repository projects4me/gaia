<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Idea plan Controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IdeaplanController extends RestController
{
    /**
     * Components that this controller uses.
     *
     * @var $uses
     * @type array
     */
    public $uses = array('Events\\IdeaplanLiveEvents');
}
