<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/*
 * Read-only members of a workspace (Workspaces D-014): one row per workspace and distinct user with a membership
 * in any live project of it, so a member of every project appears once.
 *
 * - id: a uuid derived from workspaceId and userId (md5 of "<workspaceId>:<userId>"), stable across reads.
 * - userName: the user's name, so the list sorts by name.
 * - kind: 'agent' for an agent user (users.type), 'person' otherwise.
 * - projectCount: the live projects of the workspace the user is a member of.
 * - openIssueCount: issues that are not deleted, assigned to the user, in a live project of the workspace, whose
 *   status is not done (the open issues a removal hands over, D-005).
 *
 * Deleted workspaces and deleted projects have no rows. Memberships stay the source of truth: the route is GET only.
 */
$models['Workspacemember'] = array(
    'tableName' => 'workspace_members',
    'viewSql' => 'SELECT md5("Project"."workspaceId" || \':\' || "Membership"."userId")::uuid::varchar AS id,
                    "Project"."workspaceId" AS "workspaceId",
                    "Membership"."userId" AS "userId",
                    MIN("User".name) AS "userName",
                    CASE WHEN MIN("User".type) = \'agent\' THEN \'agent\' ELSE \'person\' END AS kind,
                    COUNT(DISTINCT "Project".id) AS "projectCount",
                    (SELECT COUNT("Issue".id) FROM issues AS "Issue"
                        INNER JOIN projects AS "IssueProject" ON "IssueProject".id = "Issue"."projectId"
                        WHERE "Issue".assignee = "Membership"."userId"
                        AND "IssueProject"."workspaceId" = "Project"."workspaceId"
                        AND "IssueProject".deleted = \'0\'
                        AND "Issue".deleted = \'0\'
                        AND "Issue"."statusId" IN (SELECT "Status".id FROM issue_statuses AS "Status"
                            WHERE "Status".done = \'0\')) AS "openIssueCount"
                  FROM memberships AS "Membership"
                  INNER JOIN projects AS "Project" ON "Project".id = "Membership"."projectId"
                    AND "Project".deleted = \'0\'
                  INNER JOIN workspaces AS "Workspace" ON "Workspace".id = "Project"."workspaceId"
                    AND "Workspace".deleted = \'0\'
                  LEFT JOIN users AS "User" ON "User".id = "Membership"."userId"
                  GROUP BY "Project"."workspaceId", "Membership"."userId"',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKSPACE_MEMBERS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'workspaceId' => array(
            'name' => 'workspaceId',
            'label' => 'LBL_WORKSPACE_MEMBERS_WORKSPACE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_WORKSPACE_MEMBERS_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'userName' => array(
            'name' => 'userName',
            'label' => 'LBL_WORKSPACE_MEMBERS_USER_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => true,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_WORKSPACE_MEMBERS_KIND',
            'type' => 'varchar',
            'length' => '10',
            'null' => false,
        ),
        'projectCount' => array(
            'name' => 'projectCount',
            'label' => 'LBL_WORKSPACE_MEMBERS_PROJECT_COUNT',
            'type' => 'int',
            'null' => false,
        ),
        'openIssueCount' => array(
            'name' => 'openIssueCount',
            'label' => 'LBL_WORKSPACE_MEMBERS_OPEN_ISSUE_COUNT',
            'type' => 'int',
            'null' => false,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'workspace' => array(
                'primaryKey' => 'workspaceId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workspace',
                'relatedKey' => 'id',
            ),
            'user' => array(
                'primaryKey' => 'userId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            ),
        ),
    ),
    // Reading members needs workspacemember.get plus workspace.get (D-028): a workspace is not record-scoped,
    // so every row is visible to a caller who holds both.
    'acl' => [
        'groups' => ['Workspace'],
    ],
);

return $models;
