<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

// Test fixture for tests/tools/gaia-dev: a metadata model that is not in models.php.
$models['Tag'] = array(
    'tableName' => 'tags',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'identifier' => true
        ),
    ),
);

return $models;
