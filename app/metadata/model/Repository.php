<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A repository a workspace's agents work on: its git URL and base branch. No
 * secrets are stored here; tokens and keys stay on the runtime. A repository
 * with a projectId belongs to that project of the workspace; without one it is
 * workspace-wide, and it moves with its project (D-015). It has no
 * record scope: only the repository actions (get, create, update, delete) are
 * permissioned, and a create or update also needs workspace.update.
 */
$models['Repository'] = array(
    'tableName' => 'repositories',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_REPOSITORIES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'workspaceId' => array(
            'name' => 'workspaceId',
            'label' => 'LBL_REPOSITORIES_WORKSPACE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_REPOSITORIES_PROJECT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_REPOSITORIES_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'url' => array(
            'name' => 'url',
            'label' => 'LBL_REPOSITORIES_URL',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'baseBranch' => array(
            'name' => 'baseBranch',
            'label' => 'LBL_REPOSITORIES_BASE_BRANCH',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
            'default' => 'main',
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_REPOSITORIES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_REPOSITORIES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_REPOSITORIES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_REPOSITORIES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_REPOSITORIES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_REPOSITORIES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_REPOSITORIES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_REPOSITORIES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'workspaceId' => 'index',
        'projectId' => 'index',
        'name' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'workspace' => array(
                'primaryKey' => 'workspaceId',
                'relatedModel' => '\Gaia\MVC\Models\Workspace',
                'relatedKey' => 'id'
            ),
            'project' => array(
                'primaryKey' => 'projectId',
                'relatedModel' => '\Gaia\MVC\Models\Project',
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
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior',
        'repositoryBehavior'
    ),
    'acl' => [
        // Runtimes read the repositories of their bound workspaces (D-074).
        'service' => [
            'actions' => ['get'],
            'kinds' => ['runtime'],
            'filter' => "Repository.deleted = false AND Repository.workspaceId IN (SELECT BoundWorkspace.id FROM Gaia\\MVC\\Models\\Workspace BoundWorkspace WHERE BoundWorkspace.deleted = false AND BoundWorkspace.runtimeId IN (SELECT BoundClient.id FROM Gaia\\MVC\\Models\\Serviceclient BoundClient WHERE BoundClient.deleted = false AND BoundClient.clientId = :serviceClientId:))",
        ],
    ],
);

return $models;
