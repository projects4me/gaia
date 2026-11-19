<?php

$loader = new \Phalcon\Loader();

$loader->registerNamespaces(
    [
    "Gaia\\Tests\\Controller" => APP_PATH . '/tests/api/controllers',
    "Gaia\\Tests\\Controller\\Components\\Events" => APP_PATH . '/tests/api/controllers/components/events',
    "Gaia\\Tests\\Models\\Behaviors" => APP_PATH . '/tests/models/behaviors',
    "Gaia\\Tests\\Libraries\\Security" => APP_PATH . '/tests/libraries/security',
    "Gaia\\Tests\\Libraries\\Mentions" => APP_PATH . '/tests/libraries/mentions',
    "Gaia\\Tests\\Libraries\\Utils" => APP_PATH . '/tests/libraries/utils',
    "Gaia\\Tests\\Tools\\GaiaDev" => APP_PATH . '/tests/tools/gaia-dev',
    "GaiaDev" => APP_PATH . '/tools/gaia-dev/src',
    "Gaia\\Tests\\Tools\\ApiTester" => APP_PATH . '/tests/tools/api-tester',
    "ApiTester" => APP_PATH . '/tools/api-tester/src',
    ]
);

$loader->register();