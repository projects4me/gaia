<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Badge'] = array(
    'tableName' => 'badges',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_BADGES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_BADGES_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_BADGES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_BADGES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_BADGES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_BADGES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_BADGES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_BADGES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_BADGES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'fts' => true,
            'acl' => false
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
        'hasMany' => array(
            'levels' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Badgelevel',
                'relatedKey' => 'badgeId'
            )
        ),
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
