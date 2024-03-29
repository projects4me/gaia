<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Issuestatus'] = array(
    'tableName' => 'issue_statuses',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_ISSUE_STATUSES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_ISSUE_STATUSES_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_ISSUE_STATUSES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_ISSUE_STATUSES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_ISSUE_STATUSES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_ISSUE_STATUSES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_ISSUE_STATUSES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_ISSUE_STATUSES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_ISSUE_STATUSES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_ISSUE_STATUSES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'system' => array(
            'name' => 'system',
            'label' => 'LBL_ISSUE_STATUSES_SYSTEM',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_ISSUE_STATUSES_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'done' => array(
            'name' => 'done',
            'label' => 'LBL_ISSUE_STATUSES_DONE',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    )
);

return $models;
