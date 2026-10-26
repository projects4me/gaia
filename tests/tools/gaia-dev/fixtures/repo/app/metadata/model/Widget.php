<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

// Test fixture for tests/tools/gaia-dev: a small model in the metadata format.
$models['Widget'] = array(
    'tableName' => 'widgets',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'ownerId' => array(
            'name' => 'ownerId',
            'type' => 'varchar',
            'length' => '36',
            'relatedIdentifier' => true
        ),
        'ownerName' => array(
            'name' => 'ownerName',
            'type' => 'varchar',
            'linkedTo' => 'ownerId'
        ),
        'secret' => array(
            'name' => 'secret',
            'type' => 'text',
            'acl' => false
        ),
    ),
    'relationships' => array(
        'hasOne' => array(
            'owner' => array(
                'primaryKey' => 'ownerId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            ),
        ),
        'hasManyToMany' => array(
            'tags' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Widgettag',
                'rhsKey' => 'widgetId',
                'lhsKey' => 'tagId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\Tag',
                'secondaryKey' => 'id',
            ),
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'softDeleteBehavior'
    ),
    'acl' => [
        'groups' => ['Project'],
        'groupKeys' => [
            'Project' => 'projectId',
        ],
    ],
);

return $models;
