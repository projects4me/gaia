<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Systemnotification'] = array(
    'tableName' => 'system_notifications',
    'fields' => [
        'id' => [
            'name' => 'id',
            'label' => 'LBL_SYSTEM_NOTIFICATION_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ],
        'dateCreated' => [
            'name' => 'dateCreated',
            'label' => 'LBL_SYSTEM_NOTIFICATION_DATE_CREATED',
            'type' => 'datetime',
            'null' => false
        ],
        'dateModified' => [
            'name' => 'dateModified',
            'label' => 'LBL_SYSTEM_NOTIFICATION_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => false
        ],
        'description' => [
            'name' => 'description',
            'label' => 'LBL_SYSTEM_NOTIFICATION_DESCRIPTION',
            'type' => 'text',
            'null' => false
        ],
        'createdUser' => [
            'name' => 'createdUser',
            'label' => 'LBL_SYSTEM_NOTIFICATION_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ],
        'createdUserName' => [
            'name' => 'createdUserName',
            'label' => 'LBL_SYSTEM_NOTIFICATION_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false
        ],
        'modifiedUser' => [
            'name' => 'modifiedUser',
            'label' => 'LBL_SYSTEM_NOTIFICATION_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ],
        'modifiedUserName' => [
            'name' => 'modifiedUserName',
            'label' => 'LBL_SYSTEM_NOTIFICATION_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false
        ],
        'context' => [
            'name' => 'context',
            'label' => 'LBL_SYSTEM_NOTIFICATION_CONTEXT',
            'type' => 'text',
            'null' => true,
        ],
        // relatedTo/relatedId name the parent entity the user would open (issue, milestone, project),
        // so notifications can be found by entity. context.relatedTo is finer (issue, comment, timelog,
        // milestone, project) and drives the links Prometheus builds; the two are set independently.
        'relatedTo' => [
            'name' => 'relatedTo',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RELATED_TO',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ],
        'relatedId' => [
            'name' => 'relatedId',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RELATED_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ],
    ],
    'indexes' => [
        'id' => 'primary',
        'relatedTo' => 'index',
        'relatedId' => 'index',
    ],
    'foriegnKeys' => [],
    'triggers' => [],
    'functions' => [],
    'relationships' => [
        'hasMany' => [
            'recipientRecords' => [
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Systemnotificationrecipient',
                'relatedKey' => 'systemNotificationId',
            ]
        ]
    ],
    'behaviors' => [
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior'
    ],
);

return $models;
