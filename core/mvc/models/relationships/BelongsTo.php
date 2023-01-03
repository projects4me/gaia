<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Relationships;

use \Gaia\Core\MVC\Models\Relationships\HasOneAndManyBase;

/**
 * This class represents belongsto relationship.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Core\Mvc\Models\Relationships
 * @category BelongsTo
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BelongsTo extends HasOneAndManyBase
{
    /**
     * BelongsTo constructor.
     *
     * @param \Phalcon\DiInterface  $di
     */
    function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }
}
