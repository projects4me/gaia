<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Cache;

/**
 * Gaia's cache. Code that caches talks to this interface, never to a cache
 * library, so the backend is chosen by configuration (config/cache.php) and
 * Gaia works the same with no cache at all (NullCache).
 *
 * Values are PHP arrays and scalars. Implementations fail open: a broken
 * backend is a miss on read and a logged no-op on write, never an exception.
 *
 * @author  Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Cache
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface CacheInterface
{
    /**
     * Returns the value stored under the key, or null on a miss.
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key);

    /**
     * Stores a value under the key.
     *
     * @param string   $key
     * @param mixed    $value an array or a scalar
     * @param int|null $ttl   seconds; null uses the configured default
     * @return bool whether the value was stored
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * Removes the value stored under the key.
     *
     * @param string $key
     * @return bool whether the backend accepted the delete
     */
    public function delete(string $key): bool;

    /**
     * Removes every value of this cache.
     *
     * @return bool whether the backend was cleared
     */
    public function clear(): bool;
}
