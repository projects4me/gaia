<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Workflowedge'] = array(
    'tableName' => 'workflow_edges',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKFLOW_EDGES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'workflowId' => array(
            'name' => 'workflowId',
            'label' => 'LBL_WORKFLOW_EDGES_WORKFLOW_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'fromNodeId' => array(
            'name' => 'fromNodeId',
            'label' => 'LBL_WORKFLOW_EDGES_FROM_NODE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'fromPort' => array(
            'name' => 'fromPort',
            'label' => 'LBL_WORKFLOW_EDGES_FROM_PORT',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'toNodeId' => array(
            'name' => 'toNodeId',
            'label' => 'LBL_WORKFLOW_EDGES_TO_NODE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_WORKFLOW_EDGES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_WORKFLOW_EDGES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_WORKFLOW_EDGES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_WORKFLOW_EDGES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKFLOW_EDGES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKFLOW_EDGES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKFLOW_EDGES_DELETED',
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
        'fromNodeId' => 'index',
        'toNodeId' => 'index',
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
            'fromNode' => array(
                'primaryKey' => 'fromNodeId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflownode',
                'relatedKey' => 'id'
            ),
            'toNode' => array(
                'primaryKey' => 'toNodeId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflownode',
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
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior',
        'workflowEdgeBehavior'
    ),
);

return $models;
