<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Timelog'] = array(
    'tableName' => 'time_logs',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_TIME_LOGS',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_TIME_LOGS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_TIME_LOGS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_TIME_LOGS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_TIME_LOGS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_TIME_LOGS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_TIME_LOGS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_TIME_LOGS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'issueId' => array(
            'name' => 'issueId',
            'label' => 'LBL_TIME_LOGS_ISSUE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'minutes' => array(
            'name' => 'minutes',
            'label' => 'LBL_TIME_LOGS_MINUTES',
            'type' => 'int',
            'length' => '2',
            'null' => true,
        ),
        'hours' => array(
            'name' => 'hours',
            'label' => 'LBL_TIME_LOGS_HOURS',
            'type' => 'int',
            'length' => '2',
            'null' => true,
        ),
        'days' => array(
            'name' => 'days',
            'label' => 'LBL_TIME_LOGS_DAYS',
            'type' => 'int',
            'length' => '5',
            'null' => true,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_TIME_LOGS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'spentOn' => array(
            'name' => 'spentOn',
            'label' => 'LBL_TIME_LOGS_SPENT_ON',
            'type' => 'datetime',
            'null' => true,
        ),
        'context' => array(
            'name' => 'context',
            'label' => 'LBL_TIME_LOGS_CONTEXT',
            'type' => 'varchar',
            'length' => '5',
            'null' => false,
        )

    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
        'hasOne' => array(
            'issue' => array(
                'primaryKey' => 'issueId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'id'
            ),
            'createdBy' => array(
                'primaryKey' => 'createdUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'modifiedBy' => array(
                'primaryKey' => 'modifiedUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),

);

return $models;
