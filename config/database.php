<?php

/**
 * Database configuration — common connection settings via env vars.
 * Adapter-specific connection settings live in config/database/{Adapter}.php.
 *
 */

$adapter = getenv('DB_ADAPTER');

$connection = array(
    'adapter'  => $adapter,
    'host'     => getenv('DB_HOST'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'dbname'   => getenv('DB_NAME'),
);

$port = getenv('DB_PORT');
if ($port !== null && $port !== false && $port !== '') {
    $connection['port'] = (int) $port;
}

$adapterConfig = require __DIR__ . '/database/' . $adapter . '.php';

$config['database'] = array_merge($connection, $adapterConfig);

return $config;
