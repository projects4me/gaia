<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Badgelevel'] = array(
    'tableName' => 'badge_levels',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_BADGE_LEVELS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_BADGE_LEVELS_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false
        ),
        'badgeId' => array(
            'name' => 'badgeId',
            'label' => 'LBL_BADGE_LEVELS_BADGE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ),
        'max_criteria' => array(
            'name' => 'max_criteria',
            'label' => 'LBL_BADGE_LEVELS_MAX_CRITERIA',
            'type' => 'int',
            'length' => '100',
            'null' => false
        ),
        'min_criteria' => array(
            'name' => 'min_criteria',
            'label' => 'LBL_BADGE_LEVELS_MIN_CRITERIA',
            'type' => 'int',
            'length' => '100',
            'null' => false
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_BADGE_LEVELS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_BADGE_LEVELS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_BADGE_LEVELS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_BADGE_LEVELS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_BADGE_LEVELS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_BADGE_LEVELS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_BADGE_LEVELS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'fts' => true
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
    ),
    'customIdentifiers' => []
);

return $models;
