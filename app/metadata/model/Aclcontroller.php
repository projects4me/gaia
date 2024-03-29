<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Aclcontroller'] = array(
    'tableName' => 'acl_controllers',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_ACL_CONTROLLERS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_ACL_CONTROLLERS_RELATED_ID',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_ACL_CONTROLLERS_RELATED_TO',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'lft' => array(
            'name' => 'lft',
            'label' => 'LBL_ACL_CONTROLLERS_LEFT',
            'type' => 'int',
            'length' => '11',
            'null' => false,
        ),
        'rht' => array(
            'name' => 'rht',
            'label' => 'LBL_ACL_CONTROLLERS_RIGHT',
            'type' => 'int',
            'length' => '11',
            'null' => false,
        ),
        'parentId' => array(
            'name' => 'parentId',
            'label' => 'LBL_ACL_CONTROLLERS_PARENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_ACL_CONTROLLERS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_ACL_CONTROLLERS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_ACL_CONTROLLERS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_ACL_CONTROLLERS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_ACL_CONTROLLERS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_ACL_CONTROLLERS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_ACL_CONTROLLERS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(),
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
