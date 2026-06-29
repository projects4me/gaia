<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Factory;

/**
 * This class uses the factory design pattern to create a migration handler
 * object at runtime based on the configured database adapter.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Mvc\Db\Factory
 * @category MigrationFactory
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MigrationFactory
{
    /**
     * Migration handler instance for the configured adapter.
     *
     * @var object
     */
    protected $handler;

    /**
     * MigrationFactory constructor.
     *
     * @param \Phalcon\DiInterface $di Dependency injector
     * @param string $adapterType Configured database adapter name
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di, $adapterType)
    {
        $handlerMap = array(
            'Postgres' => 'Postgres',
            'Mysql' => 'Mysql',
        );
        $handlerClass = isset($handlerMap[$adapterType]) ? $handlerMap[$adapterType] : $adapterType;
        $handler = "\Gaia\Db\Migration\\" . $handlerClass;
        $this->handler = new $handler();
    }

    /**
     * This function returns the migration handler for the configured adapter.
     *
     * @return object
     */
    public function getHandler()
    {
        return $this->handler;
    }
}
