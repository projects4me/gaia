<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Factory;

/**
 * This class builds Phalcon PDO connections from merged per-adapter database
 * configuration.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Mvc\Db\Factory
 * @category ConnectionFactory
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ConnectionFactory
{
    /**
     * This function creates a PDO adapter using connectionClass and connectionKeys
     * from the merged database configuration.
     *
     * @param array $config Merged application database configuration
     * @param array $overrides Optional connection values to override before creation
     * @return \Phalcon\Db\Adapter\Pdo
     */
    public static function create(array $config, array $overrides = array())
    {
        $merged = array_merge($config, $overrides);
        $pdoConfig = array_intersect_key($merged, array_flip($merged['connectionKeys']));

        return new $merged['connectionClass']($pdoConfig);
    }
}
