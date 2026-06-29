<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Factory;

/**
 * This class provides helpers for working with merged per-adapter database
 * configuration produced by config/database.php.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Mvc\Db\Factory
 * @category AdapterConfig
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AdapterConfig
{
    /**
     * Keys used only for building PDO connections, not for Phalcon migration setup.
     *
     * @var array
     */
    private static $connectionMetaKeys = array('connectionClass', 'connectionKeys');

    /**
     * This function removes adapter connection metadata from a merged database config.
     *
     * @param array $config Merged application database configuration
     * @return array Configuration without connection factory metadata
     */
    public static function withoutConnectionMeta(array $config)
    {
        return array_diff_key($config, array_flip(self::$connectionMetaKeys));
    }
}
