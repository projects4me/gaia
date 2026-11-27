<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Phalcon\Cli\Task;

/**
 * Cache Task
 *
 * `php cli.php cache clear` empties the cache configured in config/cache.php
 * (nothing to do when no cache is configured). Exits 1 when the backend
 * refused the clear; the reason is in logs/application.log.
 *
 * @author  Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Core\Tasks
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CacheTask extends Task
{
    /**
     * Default action - shows help
     */
    public function mainAction()
    {
        echo 'Cache Task - Available actions:' . PHP_EOL;
        echo '  clear - Remove every entry of the configured cache' . PHP_EOL;
    }

    /**
     * Clears the configured cache.
     */
    public function clearAction()
    {
        /** @var \Gaia\Libraries\Cache\CacheInterface $cache */
        $cache = $this->getDI()->get('cache');

        if (!$cache->clear()) {
            fwrite(STDERR, 'Cache clear failed; see logs/application.log' . PHP_EOL);
            exit(1);
        }

        echo 'Cache cleared' . PHP_EOL;
    }
}
