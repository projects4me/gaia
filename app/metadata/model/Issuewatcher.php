<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Issuewatcher'] = [
    'tableName' => 'issue_watchers',
    'fields' => [
        'id' => [
            'name' => 'id',
            'label' => 'LBL_ISSUE_WATCHERS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ],
        'issueId' => [
            'name' => 'issueId',
            'label' => 'LBL_ISSUE_WATCHERS_ISSUE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ],
        'userId' => [
            'name' => 'userId',
            'label' => 'LBL_ISSUE_WATCHERS_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ],
        'dateCreated' => [
            'name' => 'dateCreated',
            'label' => 'LBL_ISSUE_WATCHERS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ],
        'dateModified' => [
            'name' => 'dateModified',
            'label' => 'LBL_ISSUE_WATCHERS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ],
        'createdUser' => [
            'name' => 'createdUser',
            'label' => 'LBL_ISSUE_WATCHERS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ],
        'modifiedUser' => [
            'name' => 'modifiedUser',
            'label' => 'LBL_ISSUE_WATCHERS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ],
        'modifiedUserName' => [
            'name' => 'modifiedUserName',
            'label' => 'LBL_ISSUE_WATCHERS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser',
        ],
        'createdUserName' => [
            'name' => 'createdUserName',
            'label' => 'LBL_ISSUE_WATCHERS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser',
        ],
        'isWatching' => [
            'name' => 'isWatching',
            'label' => 'LBL_ISSUE_WATCHERS_IS_WATCHING',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
        ]
    ],
    'indexes' => [
        'id' => 'primary',
        'issueId' => 'index',
        'userId' => 'index',
    ],
    'foreignKeys' => [],
    'triggers' => [],
    'functions' => [],
    'relationships' => [],
    'behaviors' => [
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
    ],
];

return $models;
