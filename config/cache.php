<?php

// Gaia's cache (core/libs/cache). Off unless CACHE_ADAPTER is set: none (default), stream, apcu, redis or memory.
// CACHE_VERSION is part of every key: set it per deploy (e.g. the image tag) so a new image never reads an older
// one's entries. An adapter that can't be used logs one warning and leaves caching off.
$config['cache'] = array(
    'adapter' => strtolower(getenv('CACHE_ADAPTER') ?: 'none'),
    'prefix' => getenv('CACHE_PREFIX') ?: 'gaia-',
    'version' => getenv('CACHE_VERSION') ?: '0',
    'ttl' => (int) (getenv('CACHE_TTL') ?: 3600),
    'streamDir' => getenv('CACHE_STREAM_DIR') ?: APP_PATH . '/cache/data',
    'redis' => array(
        'host' => getenv('CACHE_REDIS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('CACHE_REDIS_PORT') ?: 6379),
        'index' => (int) (getenv('CACHE_REDIS_INDEX') ?: 0),
    ),
);

return $config;
