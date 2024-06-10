<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Resource'] = array(
    'tableName' => 'resources',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_RESOURCES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'parentId' => array(
            'name' => 'parentId',
            'label' => 'LBL_RESOURCES_PARENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'entity' => array(
            'name' => 'entity',
            'label' => 'LBL_RESOURCES_ENTITY',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'lft' => array(
            'name' => 'lft',
            'label' => 'LBL_RESOURCES_LEFT',
            'type' => 'int',
            'length' => '11',
            'null' => true,
            'acl' => false
        ),
        'rht' => array(
            'name' => 'rht',
            'label' => 'LBL_RESOURCES_RIGHT',
            'type' => 'int',
            'length' => '11',
            'null' => true,
            'acl' => false
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_RESOURCES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_RESOURCES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_RESOURCES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_RESOURCES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_RESOURCES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_RESOURCES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_RESOURCES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'groupName' => array(
            'name' => 'groupName',
            'label' => 'LBL_GROUP_NAME',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
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
    'relationships' => array(
        'hasMany' => array(
            'permissions' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Permission',
                'relatedKey' => 'resourceId',
            ),
        ),
        'belongsTo' => array(
            'child' => array(
                'primaryKey' => 'parentId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Resource',
                'relatedKey' => 'id',
                'conditionExclusive' => 'Resource.lft BETWEEN child.lft AND child.rht',
                'relType' => 'INNER'
            ),
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior',
        'modelIdentifierBehavior'
    )
);

return $models;
