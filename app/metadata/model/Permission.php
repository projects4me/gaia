<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Permission'] = array(
    'tableName' => 'permissions',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_PERMISSIONS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'resourceName' => array(
            'name' => 'resourceName',
            'label' => 'LBL_PERMISSIONS_RESOURCE_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => true,
        ),
        'roleId' => array(
            'name' => 'roleId',
            'label' => 'LBL_PERMISSIONS_ROLE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'allowed' => array(
            'name' => 'allowed',
            'label' => 'LBL_PERMISSIONS_ALLOWED',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_PERMISSIONS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_PERMISSIONS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'resourceName' => 'index',
        'roleId' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'belongsTo' => array(
        ),
    ),
    'behaviors' => [
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'currentUserBehavior'
    ]
);

return $models;
