<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Project'] = array(
    'tableName' => 'projects',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_PROJECTS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_PROJECTS_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
            'fts' => true,
            'linkedTo' => 'id'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_PROJECTS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_PROJECTS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_PROJECTS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_PROJECTS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_PROJECTS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_PROJECTS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_PROJECTS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_PROJECTS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
            'fts' => true
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_PROJECTS_START_DATE',
            'type' => 'date',
            'null' => true,
            'fts' => true
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_PROJECTS_END_DATE',
            'type' => 'date',
            'null' => true,
            'fts' => true
        ),
        'workspaceId' => array(
            'name' => 'workspaceId',
            'label' => 'LBL_PROJECTS_WORKSPACE_ID',
            'type' => 'varchar',
            'null' => true,
            'length' => '36',
            'relatedIdentifier' => true
        ),
        // When the project joined its current workspace: set by projectWorkspaceBehavior on create and on a move,
        // kept on every other save, so a request cannot write it (Workspaces D-016).
        'workspaceJoinedAt' => array(
            'name' => 'workspaceJoinedAt',
            'label' => 'LBL_PROJECTS_WORKSPACE_JOINED_AT',
            'type' => 'datetime',
            'null' => true
        ),
        'shortCode' => array(
            'name' => 'shortCode',
            'label' => 'LBL_PROJECTS_SHORT_CODE',
            'type' => 'varchar',
            'null' => false,
            'length' => '50',
            'fts' => true
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_PROJECTS_STATUS',
            'type' => 'varchar',
            'null' => false,
            'length' => '15',
            'fts' => true
        ),
        'estimatedBudget' => array(
            'name' => 'estimatedBudget',
            'label' => 'LBL_PROJECTS_ESTIMATED_BUDGET',
            'type' => 'float',
            'null' => true,
            'length' => '11',
            'fts' => true
        ),
        'spentBudget' => array(
            'name' => 'spentBudget',
            'label' => 'LBL_PROJECTS_SPENT_BUDGET',
            'type' => 'float',
            'null' => true,
            'length' => '11',
            'fts' => true
        ),
        'type' => array(
            'name' => 'type',
            'label' => 'LBL_PROJECTS_TYPE',
            'type' => 'varchar',
            'null' => false,
            'length' => '15',
            'fts' => true
        ),
        'scope' => array(
            'name' => 'scope',
            'label' => 'LBL_PROJECTS_SCOPE',
            'type' => 'text',
            'null' => true,
            'fts' => true
        ),
        'vision' => array(
            'name' => 'vision',
            'label' => 'LBL_PROJECTS_VISION',
            'type' => 'text',
            'null' => true,
            'fts' => true
        ),
        'done' => array(
            'name' => 'done',
            'label' => 'LBL_PROJECTS_DONE',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0
        ),
        'projectManager' => array(
            'name' => 'projectManager',
            'label' => 'LBL_PROJECTS_PROJECT_MANAGER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        )
    ),
    // Read-only values computed on read, not columns (Workspaces D-013, D-016): project totals, whatever the caller
    // can see of the project's issues, milestones and runs.
    'computed' => array(
        'openIssueCount' => array(
            'name' => 'openIssueCount',
            'label' => 'LBL_PROJECTS_OPEN_ISSUE_COUNT',
            'type' => 'int',
            'expression' => "SELECT COUNT(OpenIssue.id) FROM Gaia\\MVC\\Models\\Issue OpenIssue WHERE OpenIssue.projectId = Project.id AND OpenIssue.deleted = 0 AND OpenIssue.statusId IN (SELECT OpenStatus.id FROM Gaia\\MVC\\Models\\Issuestatus OpenStatus WHERE OpenStatus.done = 0)",
        ),
        // The active milestone (Workspaces D-016): the in-progress one with the earliest end date, otherwise the
        // planned one with the earliest start date; activeMilestoneId and activeMilestoneName use the same ordering.
        'activeMilestoneId' => array(
            'name' => 'activeMilestoneId',
            'label' => 'LBL_PROJECTS_ACTIVE_MILESTONE_ID',
            'type' => 'varchar',
            'expression' => "SELECT ActiveMilestone.id FROM Gaia\\MVC\\Models\\Milestone ActiveMilestone WHERE ActiveMilestone.projectId = Project.id AND ActiveMilestone.deleted = 0 AND ActiveMilestone.status IN ('in_progress', 'planned') ORDER BY CASE ActiveMilestone.status WHEN 'in_progress' THEN 0 ELSE 1 END, COALESCE(CASE ActiveMilestone.status WHEN 'in_progress' THEN ActiveMilestone.endDate ELSE ActiveMilestone.startDate END, '9999-12-31'), COALESCE(ActiveMilestone.endDate, '9999-12-31'), ActiveMilestone.id LIMIT 1",
        ),
        'activeMilestoneName' => array(
            'name' => 'activeMilestoneName',
            'label' => 'LBL_PROJECTS_ACTIVE_MILESTONE_NAME',
            'type' => 'varchar',
            'expression' => "SELECT ActiveMilestone.name FROM Gaia\\MVC\\Models\\Milestone ActiveMilestone WHERE ActiveMilestone.projectId = Project.id AND ActiveMilestone.deleted = 0 AND ActiveMilestone.status IN ('in_progress', 'planned') ORDER BY CASE ActiveMilestone.status WHEN 'in_progress' THEN 0 ELSE 1 END, COALESCE(CASE ActiveMilestone.status WHEN 'in_progress' THEN ActiveMilestone.endDate ELSE ActiveMilestone.startDate END, '9999-12-31'), COALESCE(ActiveMilestone.endDate, '9999-12-31'), ActiveMilestone.id LIMIT 1",
        ),
        'runsInProgress' => array(
            'name' => 'runsInProgress',
            'label' => 'LBL_PROJECTS_RUNS_IN_PROGRESS',
            'type' => 'int',
            'expression' => "SELECT COUNT(ProgressRun.id) FROM Gaia\\MVC\\Models\\Workflowrun ProgressRun WHERE ProgressRun.projectId = Project.id AND ProgressRun.deleted = false AND ProgressRun.status IN ('running', 'waiting')",
        ),
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
        'hasOne' => array(
            'manager' => array(
                'primaryKey' => 'projectManager',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'workspace' => array(
                'primaryKey' => 'workspaceId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workspace',
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
            'docs' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Doc',
                'relatedKey' => 'projectId',
            ),
            'issues' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'projectId',
            ),
            'conversations' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Conversationroom',
                'relatedKey' => 'projectId',
            ),
            'memberships' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Membership',
                'relatedKey' => 'projectId',
            ),
            'activities' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Activity',
                'relatedKey' => 'relatedId',
                'condition' => 'activities.relatedTo = "project"',
            ),
            'milestones' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Milestone',
                'relatedKey' => 'projectId',
            ),
            'issuetypes' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issuetype',
                'relatedKey' => 'projectId',
                'condition' => 'issuetypes.system = "0"',
            ),
            'issuestatuses' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issuestatus',
                'relatedKey' => 'projectId',
                'condition' => 'issuestatuses.system = "0"'
            ),
            'aclPermissions' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Usergrouppermission',
                'relatedKey' => 'relatedId',
                'condition' => 'aclPermissions.groupName = "project"'
            )
        ),
        'hasManyToMany' => array(
            'members' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Membership',
                'rhsKey' => 'projectId',
                'lhsKey' => 'userId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\User',
                'secondaryKey' => 'id',
            ),
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior',
        'modelIdentifierBehavior',
        'currentUserBehavior',
        'shortCodeBehavior',
        'projectWorkspaceBehavior'
    ),
    'acl' => [
        'group' => true,
        'recordScopeResource' => 'project.get',
    ],
);

return $models;
