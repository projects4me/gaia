<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Relationships;

use \Gaia\Core\MVC\Models\Relationships\HasOneAndManyBase;

/**
 * This class represents HasMany relationship.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Core\MVC\Models\Relationships
 * @category HasMany
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class HasMany extends HasOneAndManyBase
{
    /**
     * HasMany constructor.
     *
     * @param \Phalcon\DiInterface  $di
     */
    function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }
}
