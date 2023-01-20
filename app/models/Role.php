<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Role Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Role extends Model
{
    /**
     * Flag decides whether to execute hasManyToMany relationship queries
     * separately or not.
     * 
     * @var $splitQueries
     */
    public $splitQueries = true;
}
