<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Cache;

/**
 * The cache used when none is configured (CACHE_ADAPTER=none) or the
 * configured one can't be built: every read is a miss and writes do nothing.
 *
 * @author  Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Cache
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NullCache implements CacheInterface
{
    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return true;
    }
}
