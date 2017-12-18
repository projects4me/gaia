<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Membership'] = array(
   'tableName' => 'memberships',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_MEMBERSHIPS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_MEMBERSHIPS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_MEMBERSHIPS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_MEMBERSHIPS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_MEMBERSHIPS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'roleId' => array(
            'name' => 'roleId',
            'label' => 'LBL_MEMBERSHIPS_ROLE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_MEMBERSHIPS_PROJECT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_MEMBERSHIPS_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_MEMBERSHIPS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
        'roleId' => 'index',
        'projectId' => 'index',
        'userId' => 'index',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
    ),
);

return $models;
