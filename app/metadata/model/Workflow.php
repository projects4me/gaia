<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Workflow'] = array(
    'tableName' => 'workflows',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKFLOWS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_WORKFLOWS_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_WORKFLOWS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_WORKFLOWS_STATUS',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
            'default' => 'Draft',
        ),
        'scope' => array(
            'name' => 'scope',
            'label' => 'LBL_WORKFLOWS_SCOPE',
            'type' => 'text',
            'null' => true,
        ),
        'version' => array(
            'name' => 'version',
            'label' => 'LBL_WORKFLOWS_VERSION',
            'type' => 'int',
            'null' => false,
            'default' => 0,
        ),
        'ownerId' => array(
            'name' => 'ownerId',
            'label' => 'LBL_WORKFLOWS_OWNER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_WORKFLOWS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_WORKFLOWS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_WORKFLOWS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_WORKFLOWS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKFLOWS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKFLOWS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKFLOWS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'status' => 'index',
        'ownerId' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'owner' => array(
                'primaryKey' => 'ownerId',
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
            'nodes' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflownode',
                'relatedKey' => 'workflowId'
            ),
            'edges' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflowedge',
                'relatedKey' => 'workflowId'
            ),
            'references' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflowreference',
                'relatedKey' => 'workflowId'
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
        'workflowBehavior'
    ),
);

return $models;
