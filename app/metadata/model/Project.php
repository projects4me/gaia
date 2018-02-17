<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Project'] = array(
    'tableName' => 'projects',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_PROJECTS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_PROJECTS_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_PROJECTS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_PROJECTS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_PROJECTS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_PROJECTS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'assignee' => array(
            'name' => 'assignee',
            'label' => 'LBL_PROJECTS_ASSIGNEE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_PROJECTS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_PROJECTS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_PROJECTS_START_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_PROJECTS_END_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'shortCode' => array(
            'name' => 'shortCode',
            'label' => 'LBL_PROJECTS_SHORT_CODE',
            'type' => 'varchar',
            'null' => false,
            'length' => '50'
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_PROJECTS_STATUS',
            'type' => 'varchar',
            'null' => false,
            'length' => '15'
        ),
        'estimatedBudget' => array(
            'name' => 'estimatedBudget',
            'label' => 'LBL_PROJECTS_ESTIMATED_BUDGET',
            'type' => 'float',
            'null' => true,
            'length' => '11'
        ),
        'spentBudget' => array(
            'name' => 'spentBudget',
            'label' => 'LBL_PROJECTS_SPENT_BUDGET',
            'type' => 'float',
            'null' => true,
            'length' => '11'
        ),
        'type' => array(
            'name' => 'type',
            'label' => 'LBL_PROJECTS_TYPE',
            'type' => 'varchar',
            'null' => false,
            'length' => '15'
        ),
        'scope' => array(
            'name' => 'scope',
            'label' => 'LBL_PROJECTS_SCOPE',
            'type' => 'text',
            'null' => true,
        ),
        'vision' => array(
            'name' => 'vision',
            'label' => 'LBL_PROJECTS_VISION',
            'type' => 'text',
            'null' => true,
        ),
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
            'owner' => array(
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
        ),
        'hasMany' => array(
            'issues' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'projectId',
            ),
            'conversations' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Conversationroom',
                'relatedKey' => 'projectId',
            ),
            'memberships' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Membership',
                'relatedKey' => 'projectId',
            ),
            'activities' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Activity',
                'relatedKey' => 'relatedId',
                'condition' => 'activities.relatedTo = "project"',
            ),
            'milestones' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Milestone',
                'relatedKey' => 'projectId',
            ),
            'issuetypes' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issuetype',
                'relatedKey' => 'projectId',
                'condition' => 'issuetypes.system = "0"',
            ),
        ),
        'hasManyToMany' => array(
            'members' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Membership',
                'rhsKey' => 'projectId',
                'lhsKey' => 'userId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\User',
                'secondaryKey' => 'id',
            ),
            'roles' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Membership',
                'rhsKey' => 'projectId',
                'lhsKey' => 'roleId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\Role',
                'secondaryKey' => 'id',
            ),
        )
    ),
    'behaviors' => array(
        'aclBehavior',
    ),
);

return $models;
