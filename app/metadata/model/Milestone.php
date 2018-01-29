<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Milestone'] = array(
   'tableName' => 'milestones',
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
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_MILESTONES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_MILESTONES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_MILESTONES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
       'description' => array(
            'name' => 'description',
            'label' => 'LBL_MILESTONES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_MILESTONES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_MILESTONES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_MILESTONES_END_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_MILESTONES_START_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_MILESTONES_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'milestoneType' => array(
            'name' => 'milestoneType',
            'label' => 'LBL_MILESTONES_MILESTONE_TYPE',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
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
    'relationships' => array(
        'hasOne' => array(
          'createdUser' => array(
              'primaryKey' => 'createdUser',
              'relatedModel' => '\\Gaia\\MVC\\Models\\User',
              'relatedKey' => 'id'
          ),
          'modifiedUser' => array(
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
);

return $models;
