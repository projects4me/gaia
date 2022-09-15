<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Factory;

/**
 * This class uses factory design pattern in order to create an dialect
 * object on runtime.
 *
 * @author Rana Nouman <ranamnouman@yahoo.com>
 * @package Foundation\Mvc\Db\Factory
 * @category DialectFactory
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DialectFactory
{
    /**
     * The dependency injector used by this class
     *
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    /**
     * DialectFactory constructor.
     *
     * @param \Phalcon\DiInterface $di
     * @param string $dialectType
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di, $dialectType)
    {
        $this->di = $di;
        $dialect = "\Gaia\Db\Dialect\\".$dialectType;
        $this->dialect = new $dialect();
    }

    /**
     * This function returns dialect object.
     *
     * @return object
     */
    public function getDialect()
    {
        return $this->dialect;
    }
}
