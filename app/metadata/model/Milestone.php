<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Milestone'] = array(
    'tableName' => 'milestones',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_MILESTONES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_MILESTONES_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
            'fts' => true
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_MILESTONES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_MILESTONES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_MILESTONES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_MILESTONES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
            'fts' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_MILESTONES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_MILESTONES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_MILESTONES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_MILESTONES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_MILESTONES_END_DATE',
            'type' => 'date',
            'null' => true,
            'fts' => true
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_MILESTONES_START_DATE',
            'type' => 'date',
            'null' => true,
            'fts' => true
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_MILESTONES_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
            'fts' => true
        ),
        'milestoneType' => array(
            'name' => 'milestoneType',
            'label' => 'LBL_MILESTONES_MILESTONE_TYPE',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
            'fts' => true
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_MILESTONES_PROJECT',
            'type' => 'varchar',
            'length' => '36',
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
        'hasOne' => array(
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
            'project' => array(
                'primaryKey' => 'projectId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'id'
            ),
        ),
        'hasMany' => array(
            'issues' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'milestoneId',
            ),
        )
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
