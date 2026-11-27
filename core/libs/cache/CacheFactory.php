<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Cache;

use Phalcon\Cache\AdapterFactory;
use Phalcon\Cache\Cache;
use Phalcon\Logger\LoggerInterface;
use Phalcon\Storage\SerializerFactory;

/**
 * Builds Gaia's cache from the `cache` settings (config/cache.php).
 *
 * `adapter` none (the default) gives a NullCache; stream, apcu, redis and
 * memory give a PhalconCache on that Phalcon adapter. Building fails open: when
 * the adapter is unknown or can't be used (missing extension, unwritable
 * stream directory, Redis unreachable) the factory logs one warning and
 * returns a NullCache, so Gaia works as it does without a cache.
 *
 * @author  Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Cache
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CacheFactory
{
    /**
     * Seconds to wait for Redis to answer the connect before giving up.
     */
    private const REDIS_CONNECT_TIMEOUT = 1;

    /**
     * Default lifetime of an entry, in seconds, when the settings have none.
     */
    private const DEFAULT_TTL = 3600;

    /**
     * @var array
     */
    private $config;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @param array                $config the `cache` settings: adapter, prefix, version, ttl, streamDir, redis
     * @param LoggerInterface|null $logger receives the warning when the cache falls back to NullCache
     */
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Builds the cache from Gaia's settings; the one entry point the web and CLI bootstraps share.
     *
     * @param mixed                $settings the `cache` settings (a Phalcon Config, an array or null)
     * @param LoggerInterface|null $logger
     * @return CacheInterface
     */
    public static function fromSettings($settings, ?LoggerInterface $logger = null): CacheInterface
    {
        if (is_object($settings) && method_exists($settings, 'toArray')) {
            $settings = $settings->toArray();
        }

        return (new self(is_array($settings) ? $settings : [], $logger))->create();
    }

    /**
     * Builds the configured cache, or a NullCache.
     *
     * @return CacheInterface
     */
    public function create(): CacheInterface
    {
        $adapter = strtolower(trim((string) ($this->config['adapter'] ?? 'none')));
        if ($adapter === '' || $adapter === 'none') {
            return new NullCache();
        }

        try {
            return new PhalconCache(
                new Cache($this->buildAdapter($adapter)),
                $adapter,
                (string) ($this->config['version'] ?? ''),
                $this->logger
            );
        } catch (\Throwable $e) {
            if ($this->logger !== null) {
                $this->logger->warning(
                    'Cache adapter "' . $adapter . '" unavailable, caching is off: ' . $e->getMessage()
                );
            }
            return new NullCache();
        }
    }

    /**
     * Builds the Phalcon adapter and checks that it can be used.
     *
     * @param string $adapter
     * @return \Phalcon\Cache\Adapter\AdapterInterface
     * @throws \RuntimeException when the adapter is unknown or can't be used
     */
    private function buildAdapter(string $adapter)
    {
        $options = [
            'defaultSerializer' => 'php',
            'lifetime' => $this->ttl(),
            'prefix' => (string) ($this->config['prefix'] ?? ''),
        ];

        switch ($adapter) {
            case 'memory':
                break;
            case 'stream':
                $options['storageDir'] = $this->streamDirectory();
                break;
            case 'apcu':
                if (!extension_loaded('apcu') || !function_exists('apcu_enabled') || !apcu_enabled()) {
                    throw new \RuntimeException('the APCu extension is not loaded or not enabled');
                }
                break;
            case 'redis':
                if (!extension_loaded('redis')) {
                    throw new \RuntimeException('the redis extension is not loaded');
                }
                $redis = $this->config['redis'] ?? [];
                $options['host'] = (string) ($redis['host'] ?? '127.0.0.1');
                $options['port'] = (int) ($redis['port'] ?? 6379);
                $options['index'] = (int) ($redis['index'] ?? 0);
                $options['timeout'] = self::REDIS_CONNECT_TIMEOUT;
                break;
            default:
                throw new \RuntimeException('unknown adapter (use none, stream, apcu, redis or memory)');
        }

        $instance = (new AdapterFactory(new SerializerFactory()))->newInstance($adapter, $options);

        if ($adapter === 'redis') {
            // Connects now, so an unreachable Redis falls back here instead of failing every read.
            $instance->getAdapter();
        }

        return $instance;
    }

    /**
     * The default lifetime of an entry in seconds.
     *
     * @return int
     */
    private function ttl(): int
    {
        $ttl = (int) ($this->config['ttl'] ?? 0);

        return $ttl > 0 ? $ttl : self::DEFAULT_TTL;
    }

    /**
     * The stream adapter's directory, created when missing.
     *
     * @return string
     * @throws \RuntimeException when it can't be created or written
     */
    private function streamDirectory(): string
    {
        $directory = rtrim((string) ($this->config['streamDir'] ?? ''), '/\\');
        if ($directory === '') {
            throw new \RuntimeException('no stream directory configured');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('the stream directory ' . $directory . ' can\'t be created');
        }
        if (!is_writable($directory)) {
            throw new \RuntimeException('the stream directory ' . $directory . ' is not writable');
        }

        return $directory . '/';
    }
}
