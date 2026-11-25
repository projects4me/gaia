<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A workspace groups projects and their environment. It has no members and no
 * record scope: membership and record ACL stay on the projects; only the
 * workspace actions (get, create, update, delete) are permissioned. A
 * workspace may be bound to one runtime (`runtimeId`), the only runtime that
 * may act for its agents (Workspaces D-009). Its `shortCode` is unique among
 * the live workspaces (D-012). Its manager (`managerId`) is a user, the creator
 * unless another one is given (D-022).
 */
$models['Workspace'] = array(
    'tableName' => 'workspaces',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKSPACES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_WORKSPACES_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        // Required by workspaceShortCodeBehavior (D-012); the column stays
        // nullable so the declarative migration can add it to existing rows.
        'shortCode' => array(
            'name' => 'shortCode',
            'label' => 'LBL_WORKSPACES_SHORT_CODE',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_WORKSPACES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'runtimeId' => array(
            'name' => 'runtimeId',
            'label' => 'LBL_WORKSPACES_RUNTIME_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
            'relatedIdentifier' => true
        ),
        // The workspace manager (D-022): a user, the creator unless another is given. Nullable so the declarative
        // migration can add it to existing rows; workspaceManagerBehavior fills it on create.
        'managerId' => array(
            'name' => 'managerId',
            'label' => 'LBL_WORKSPACES_MANAGER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
            'relatedIdentifier' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_WORKSPACES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_WORKSPACES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_WORKSPACES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_WORKSPACES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKSPACES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKSPACES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKSPACES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    // Read-only values computed on read, not columns (D-013): workspace totals, whatever projects the caller can see.
    'computed' => array(
        'projectCount' => array(
            'name' => 'projectCount',
            'label' => 'LBL_WORKSPACES_PROJECT_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedProject.id) FROM Gaia\\MVC\\Models\\Project CountedProject WHERE CountedProject.workspaceId = Workspace.id AND CountedProject.deleted = 0",
        ),
        'memberCount' => array(
            'name' => 'memberCount',
            'label' => 'LBL_WORKSPACES_MEMBER_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(DISTINCT CountedMembership.userId) FROM Gaia\\MVC\\Models\\Membership CountedMembership WHERE CountedMembership.projectId IN (SELECT MemberProject.id FROM Gaia\\MVC\\Models\\Project MemberProject WHERE MemberProject.workspaceId = Workspace.id AND MemberProject.deleted = 0)",
        ),
        // Environment counts (D-018).
        'repositoryCount' => array(
            'name' => 'repositoryCount',
            'label' => 'LBL_WORKSPACES_REPOSITORY_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedRepository.id) FROM Gaia\\MVC\\Models\\Repository CountedRepository WHERE CountedRepository.workspaceId = Workspace.id AND CountedRepository.deleted = false",
        ),
        'projectRepositoryCount' => array(
            'name' => 'projectRepositoryCount',
            'label' => 'LBL_WORKSPACES_PROJECT_REPOSITORY_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedProjectRepository.id) FROM Gaia\\MVC\\Models\\Repository CountedProjectRepository WHERE CountedProjectRepository.workspaceId = Workspace.id AND CountedProjectRepository.deleted = false AND CountedProjectRepository.projectId IS NOT NULL",
        ),
        'agentCount' => array(
            'name' => 'agentCount',
            'label' => 'LBL_WORKSPACES_AGENT_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedAgent.id) FROM Gaia\\MVC\\Models\\Agent CountedAgent WHERE CountedAgent.workspaceId = Workspace.id AND CountedAgent.deleted = false",
        ),
        'toolserverCount' => array(
            'name' => 'toolserverCount',
            'label' => 'LBL_WORKSPACES_TOOLSERVER_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedToolserver.id) FROM Gaia\\MVC\\Models\\Toolserver CountedToolserver WHERE CountedToolserver.workspaceId = Workspace.id AND CountedToolserver.deleted = false",
        ),
        'skillCount' => array(
            'name' => 'skillCount',
            'label' => 'LBL_WORKSPACES_SKILL_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedSkill.id) FROM Gaia\\MVC\\Models\\Skill CountedSkill WHERE CountedSkill.workspaceId = Workspace.id AND CountedSkill.deleted = false",
        ),
        // Same definition as the project move gate (D-011): running or waiting runs of the workspace's live projects.
        'runsInProgress' => array(
            'name' => 'runsInProgress',
            'label' => 'LBL_WORKSPACES_RUNS_IN_PROGRESS',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedRun.id) FROM Gaia\\MVC\\Models\\Workflowrun CountedRun WHERE CountedRun.deleted = false AND CountedRun.status IN ('running', 'waiting') AND CountedRun.projectId IN (SELECT RunProject.id FROM Gaia\\MVC\\Models\\Project RunProject WHERE RunProject.workspaceId = Workspace.id AND RunProject.deleted = 0)",
        ),
        // Cross-project link counts (D-017): live links between two different projects of this workspace.
        'crossProjectLinkCount' => array(
            'name' => 'crossProjectLinkCount',
            'label' => 'LBL_WORKSPACES_CROSS_PROJECT_LINK_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedLink.id) FROM Gaia\\MVC\\Models\\Issuelink CountedLink WHERE CountedLink.deleted = false AND CountedLink.projectId <> CountedLink.targetProjectId AND CountedLink.projectId IN (SELECT LinkProject.id FROM Gaia\\MVC\\Models\\Project LinkProject WHERE LinkProject.workspaceId = Workspace.id AND LinkProject.deleted = 0) AND CountedLink.targetProjectId IN (SELECT LinkTargetProject.id FROM Gaia\\MVC\\Models\\Project LinkTargetProject WHERE LinkTargetProject.workspaceId = Workspace.id AND LinkTargetProject.deleted = 0)",
        ),
        'crossProjectBlockingCount' => array(
            'name' => 'crossProjectBlockingCount',
            'label' => 'LBL_WORKSPACES_CROSS_PROJECT_BLOCKING_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(CountedBlock.id) FROM Gaia\\MVC\\Models\\Issuelink CountedBlock WHERE CountedBlock.deleted = false AND CountedBlock.type = 'blocks' AND CountedBlock.projectId <> CountedBlock.targetProjectId AND CountedBlock.projectId IN (SELECT BlockProject.id FROM Gaia\\MVC\\Models\\Project BlockProject WHERE BlockProject.workspaceId = Workspace.id AND BlockProject.deleted = 0) AND CountedBlock.targetProjectId IN (SELECT BlockTargetProject.id FROM Gaia\\MVC\\Models\\Project BlockTargetProject WHERE BlockTargetProject.workspaceId = Workspace.id AND BlockTargetProject.deleted = 0)",
        ),
        // Workspaces D-017: the bound runtime is online when its service client was used (lastUsedAt, UTC) in the last 5 minutes.
        'runtimeOnline' => array(
            'name' => 'runtimeOnline',
            'label' => 'LBL_WORKSPACES_RUNTIME_ONLINE',
            'type' => 'bool',
            'expression' => "SELECT COUNT(OnlineRuntime.id) > 0 FROM Gaia\\MVC\\Models\\Serviceclient OnlineRuntime WHERE OnlineRuntime.id = Workspace.runtimeId AND OnlineRuntime.deleted = false AND OnlineRuntime.status = 'active' AND OnlineRuntime.lastUsedAt >= TIMEZONE('UTC', NOW()) - MAKE_INTERVAL(0, 0, 0, 0, 0, 5)",
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'name' => 'index',
        'shortCode' => 'index',
        'runtimeId' => 'index',
        'managerId' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'manager' => array(
                'primaryKey' => 'managerId',
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
            'runtime' => array(
                'primaryKey' => 'runtimeId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Serviceclient',
                'relatedKey' => 'id'
            ),
        ),
        'hasMany' => array(
            'projects' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'workspaceId',
            ),
            'agents' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Agent',
                'relatedKey' => 'workspaceId',
            ),
            'workflows' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflow',
                'relatedKey' => 'workspaceId',
            ),
            'toolservers' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\Gaia\MVC\Models\Toolserver',
                'relatedKey' => 'workspaceId',
            ),
            'skills' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\Gaia\MVC\Models\Skill',
                'relatedKey' => 'workspaceId',
            ),
            'repositories' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Repository',
                'relatedKey' => 'workspaceId',
            ),
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'workspaceManagerBehavior',
        'softDeleteBehavior',
        'workspaceRuntimeBehavior',
        'workspaceShortCodeBehavior',
        'workspaceActivityBehavior'
    ),
    'acl' => [
        // Runtimes read the workspaces bound to them (D-074).
        'service' => [
            'actions' => ['get'],
            'kinds' => ['runtime'],
            'filter' => "Workspace.deleted = false AND Workspace.runtimeId IN (SELECT BoundClient.id FROM Gaia\\MVC\\Models\\Serviceclient BoundClient WHERE BoundClient.deleted = false AND BoundClient.clientId = :serviceClientId:)",
        ],
    ],
);

return $models;
