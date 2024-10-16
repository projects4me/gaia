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
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_MEMBERSHIPS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
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
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_MEMBERSHIPS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
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
        )
    ),
    'indexes' => array(
        'id' => 'primary',
        'roleId' => 'index',
        'projectId' => 'index',
        'userId' => 'index',
    ),
    'foreignKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior'
    ),
);

return $models;
