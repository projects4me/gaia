<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userrole'] = array(
    'tableName' => 'user_roles',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_USER_ROLES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_USER_ROLES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_USER_ROLES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_USER_ROLES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_USER_ROLES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_USER_ROLES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_USER_ROLES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_USER_ROLES_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'roleId' => array(
            'name' => 'roleId',
            'label' => 'LBL_USER_ROLES_ROLE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'userId' => 'index',
        'roleId' => 'index',
    ),
    'foreignKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => [
            'user' => [
                'primaryKey' => 'userId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            ],
            'role' => [
                'primaryKey' => 'roleId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Role',
                'relatedKey' => 'id',
            ]
        ]
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior'
    ),
    'acl' => [
        'assignment' => [
            'field' => 'createdUser',
            'condition' => 'Userrole.createdUser=:userId:'
        ]
    ]
);

return $models;
