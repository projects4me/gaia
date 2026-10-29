<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Workflownode'] = array(
    'tableName' => 'workflow_nodes',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKFLOW_NODES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'workflowId' => array(
            'name' => 'workflowId',
            'label' => 'LBL_WORKFLOW_NODES_WORKFLOW_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'category' => array(
            'name' => 'category',
            'label' => 'LBL_WORKFLOW_NODES_CATEGORY',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_WORKFLOW_NODES_KIND',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'config' => array(
            'name' => 'config',
            'label' => 'LBL_WORKFLOW_NODES_CONFIG',
            'type' => 'text',
            'null' => true,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_WORKFLOW_NODES_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => true,
        ),
        'positionX' => array(
            'name' => 'positionX',
            'label' => 'LBL_WORKFLOW_NODES_POSITION_X',
            'type' => 'int',
            'null' => true,
        ),
        'positionY' => array(
            'name' => 'positionY',
            'label' => 'LBL_WORKFLOW_NODES_POSITION_Y',
            'type' => 'int',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_WORKFLOW_NODES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_WORKFLOW_NODES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_WORKFLOW_NODES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_WORKFLOW_NODES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKFLOW_NODES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKFLOW_NODES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKFLOW_NODES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'workflowId' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'workflow' => array(
                'primaryKey' => 'workflowId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflow',
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
            'references' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflowreference',
                'relatedKey' => 'nodeId'
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
        'workflowNodeBehavior'
    ),
);

return $models;
