<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Systemnotificationrecipient'] = [
    'tableName' => 'system_notification_recipients',
    'fields' => [
        'id' => [
            'name' => 'id',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ],
        'systemNotificationId' => [
            'name' => 'systemNotificationId',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_SYSTEM_NOTIFICATION_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ],
        'userId' => [
            'name' => 'userId',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ],
        'isRead' => [
            'name' => 'isRead',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_IS_READ',
            'type' => 'bool',
            'null' => false,
            'default' => 0
        ],
        'createdUser' => [
            'name' => 'createdUser',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ],
        'createdUserName' => [
            'name' => 'createdUserName',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false
        ],
        'modifiedUser' => [
            'name' => 'modifiedUser',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ],
        'modifiedUserName' => [
            'name' => 'modifiedUserName',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false
        ],
        'dateCreated' => [
            'name' => 'dateCreated',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_DATE_CREATED',
            'type' => 'datetime',
            'null' => false
        ],
        'dateModified' => [
            'name' => 'dateModified',
            'label' => 'LBL_SYSTEM_NOTIFICATION_RECIPIENT_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => false
        ]
    ],
    'indexes' => [
        'id' => 'primary'
    ],
    'relationships' => [],
    'triggers' => [],
    'functions' => [],
    'relationships' => [],
    'behaviors' => [
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior'
    ],
];

return $models;
