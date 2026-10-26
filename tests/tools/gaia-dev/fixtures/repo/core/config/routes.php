<?php

// Test fixture for tests/tools/gaia-dev: one REST resource, one app route, one system route.
$config['routes'] = array(
    'rest' => array(
        'v1' => array(
            'widget' => array(
                'path' => '/api/:version/widget',
                'allowedMethods' => array(
                    'GET',
                    'POST',
                ),
                'identifier' => 'id',
            ),
        ),
    ),
    'app' => array(
        'export' => array(
            'path' => '/export/{format}',
            'controller' => 'export',
            'action' => 'get',
        ),
    ),
    'system' => array(
        'not_found' => array(
            'controller' => 'error',
            'action' => 'not_found',
        ),
    ),
);

return $config;
