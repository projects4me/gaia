<?php

$loader = new \Phalcon\Loader();

$loader->registerNamespaces(
    [
    "Gaia\\Tests\\Controller" => APP_PATH . '/tests/api/controllers',
    "Gaia\\Tests\\Controller\\Components\\Events" => APP_PATH . '/tests/api/controllers/components/events',
    "Gaia\\Tests\\Models\\Behaviors" => APP_PATH . '/tests/models/behaviors',
    "Gaia\\Tests\\Libraries\\Security" => APP_PATH . '/tests/libraries/security',
    ]
);

$loader->register();