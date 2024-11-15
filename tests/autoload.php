<?php

$loader = new \Phalcon\Loader();

$loader->registerNamespaces(
    [
    "Gaia\\Tests\\Acl" => APP_PATH . '/tests/acl/',
    "Gaia\\Tests\\Controller" => APP_PATH . '/tests/api/controllers'
    ]
);

$loader->register();