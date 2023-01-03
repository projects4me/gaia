<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Relationships\Factory;

use Gaia\Libraries\Utils\Util;

/**
 * This class uses factory design pattern in order to create an relationship
 * object on runtime.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Core\MVC\Models\Relationships\Factory
 * @category RelationshipFactory
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RelationshipFactory
{
    /**
     * The dependency injector used by this class
     *
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    /**
     * RelationshipFactory constructor.
     *
     * @param (\Phalcon\Di\FactoryDefault $di
     * @param string $dialectType
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * Create relationship according to given type.
     * @param $relType
     * @return Relationship
     */
    public function createRelationship($relType)
    {
        $relType = Util::convertToCamelCase($relType);
        $relationship = "\Gaia\Core\MVC\Models\Relationships\\" . $relType;
        return new $relationship($this->di);
    }
}
