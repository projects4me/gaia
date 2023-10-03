<?php

$loader = new \Phalcon\Loader();

$loader->registerNamespaces(
[
    "Tests\\Gaia\\Acl" => APP_PATH . '/tests/acl/'
]
);

$loader->register();