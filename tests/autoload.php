<?php

$loader = new \Phalcon\Loader();

$loader->registerNamespaces(
    [
    "Gaia\\Tests\\Acl" => APP_PATH . '/tests/acl/',
    "Gaia\\Tests\\Controller" => APP_PATH . '/tests/api/controllers',
    "Gaia\\Tests\\Models\\Behaviors" => APP_PATH . '/tests/models/behaviors',
    ]
);

$loader->register();