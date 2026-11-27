<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Cache;

use Phalcon\Cache\Cache;
use Phalcon\Logger\LoggerInterface;

/**
 * Gaia's cache on top of Phalcon\Cache\Cache (adapters stream, apcu, redis
 * and memory; CacheFactory builds it).
 *
 * Every key carries the cache version (CACHE_VERSION) after the adapter's
 * prefix (CACHE_PREFIX), so a new image never reads entries an older one
 * wrote. Phalcon accepts only [A-Za-z0-9-_.] in keys: a key with any other
 * character is stored under a hash of it.
 *
 * Fails open: an exception from the backend is a miss on get and is logged and
 * ignored on set, delete and clear.
 *
 * @author  Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Cache
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PhalconCache implements CacheInterface
{
    /**
     * Characters Phalcon refuses in a key (and in the version that is part of it).
     */
    private const INVALID_KEY_CHARACTERS = '/[^A-Za-z0-9\-_.]/';

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var string
     */
    private $adapterName;

    /**
     * @var string
     */
    private $version;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @param Cache                $cache       the Phalcon cache, with its adapter's prefix and default lifetime set
     * @param string               $adapterName the adapter it was built with (stream, apcu, redis, memory)
     * @param string               $version     added to every key
     * @param LoggerInterface|null $logger      receives a warning when the backend fails
     */
    public function __construct(Cache $cache, string $adapterName, string $version, ?LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->adapterName = $adapterName;
        $this->version = (string) preg_replace(self::INVALID_KEY_CHARACTERS, '-', $version);
        $this->logger = $logger;
    }

    /**
     * The name of the Phalcon adapter this cache uses.
     *
     * @return string
     */
    public function adapterName(): string
    {
        return $this->adapterName;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        try {
            return $this->cache->get($this->storageKey($key));
        } catch (\Throwable $e) {
            $this->warn('get', $e);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            return (bool) $this->cache->set($this->storageKey($key), $value, $ttl);
        } catch (\Throwable $e) {
            $this->warn('set', $e);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            return (bool) $this->cache->delete($this->storageKey($key));
        } catch (\Throwable $e) {
            $this->warn('delete', $e);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        try {
            return (bool) $this->cache->clear();
        } catch (\Throwable $e) {
            $this->warn('clear', $e);
            return false;
        }
    }

    /**
     * The key as stored, before the adapter adds its prefix: the version, a dot
     * and the key, or a hash of the key when it has characters Phalcon refuses.
     *
     * @param string $key
     * @return string
     */
    public function storageKey(string $key): string
    {
        if ($key === '' || preg_match(self::INVALID_KEY_CHARACTERS, $key)) {
            $key = 'h.' . sha1($key);
        }

        return $this->version . '.' . $key;
    }

    /**
     * Logs a failed cache operation as a warning.
     *
     * @param string     $operation
     * @param \Throwable $e
     */
    private function warn(string $operation, \Throwable $e): void
    {
        if ($this->logger !== null) {
            $this->logger->warning(
                'Cache ' . $operation . ' failed (' . $this->adapterName . '): ' . get_class($e) . ': ' . $e->getMessage()
            );
        }
    }
}
