<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Issue'] = array(
    'tableName' => 'issues',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_ISSUES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'subject' => array(
            'name' => 'subject',
            'label' => 'LBL_ISSUES_SUBJECT',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_ISSUES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_ISSUES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_ISSUES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_ISSUES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_ISSUES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'owner' => array(
            'name' => 'owner',
            'label' => 'LBL_ISSUES_OWNER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'assignee' => array(
            'name' => 'assignee',
            'label' => 'LBL_ISSUES_ASSIGNEE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'reportedUser' => array(
            'name' => 'reportedUser',
            'label' => 'LBL_ISSUES_REPORTED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_ISSUES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'issueNumber' => array(
            'name' => 'issueNumber',
            'label' => 'LBL_ISSUES_ISSUE_NUMBER',
            'type' => 'int',
            'length' => '11',
            'autoIncrement' => 'true',
            'null' => true,
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_ISSUES_END_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_ISSUES_START_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_ISSUES_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'typeId' => array(
            'name' => 'typeId',
            'label' => 'LBL_ISSUES_TYPE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'priority' => array(
            'name' => 'priority',
            'label' => 'LBL_ISSUES_PRIORITY',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_ISSUES_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'milestoneId' => array(
            'name' => 'milestoneId',
            'label' => 'LBL_ISSUES_MILESTONE',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'parentId' => array(
            'name' => 'parentId',
            'label' => 'LBL_ISSUES_PARENT',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'issueNumber' => 'unique',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
        'hasOne' => array(
            'assignedTo' => array(
                'primaryKey' => 'assignee',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
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
            'ownedBy' => array(
                'primaryKey' => 'owner',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'reportedBy' => array(
                'primaryKey' => 'reportedUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'project' => array(
                'primaryKey' => 'projectId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'id'
            ),
            'milestone' => array(
                'primaryKey' => 'milestoneId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Milestone',
                'relatedKey' => 'id'
            ),
            'issuetype' => array(
                'primaryKey' => 'typeId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issuetype',
                'relatedKey' => 'id'
            ),
            'parentissue' => array(
                'primaryKey' => 'parentId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'id',
            ),
        ),
        'hasMany' => array(
            'estimated' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Timelog',
                'relatedKey' => 'issueId',
                'condition' => 'estimated.context = "est"'
            ),
            'spent' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Timelog',
                'relatedKey' => 'issueId',
                'condition' => 'spent.context = "spent"'
            ),
            'childissues' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'parentId',
            ),
            'comments' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Comment',
                'relatedKey' => 'relatedId',
                'condition' => 'comments.relatedTo = "issue"'
            ),
            'activities' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Activity',
                'relatedKey' => 'relatedId',
                'condition' => 'activities.relatedTo = "issue"',
            ),
            'files' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Upload',
                'relatedKey' => 'relatedId',
                'condition' => 'files.relatedTo = "issue"',
            ),
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
    ),
);

return $models;
