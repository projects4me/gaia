<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Workflowreference'] = array(
    'tableName' => 'workflow_references',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKFLOW_REFERENCES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'workflowId' => array(
            'name' => 'workflowId',
            'label' => 'LBL_WORKFLOW_REFERENCES_WORKFLOW_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'nodeId' => array(
            'name' => 'nodeId',
            'label' => 'LBL_WORKFLOW_REFERENCES_NODE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'entityType' => array(
            'name' => 'entityType',
            'label' => 'LBL_WORKFLOW_REFERENCES_ENTITY_TYPE',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'entityId' => array(
            'name' => 'entityId',
            'label' => 'LBL_WORKFLOW_REFERENCES_ENTITY_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_WORKFLOW_REFERENCES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_WORKFLOW_REFERENCES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_WORKFLOW_REFERENCES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_WORKFLOW_REFERENCES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKFLOW_REFERENCES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKFLOW_REFERENCES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKFLOW_REFERENCES_DELETED',
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
        'nodeId' => 'index',
        'entityId' => 'index',
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
            'node' => array(
                'primaryKey' => 'nodeId',
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
        'softDeleteBehavior'
    ),
);

return $models;
