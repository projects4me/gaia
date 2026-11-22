<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/*
 * Read-only milestones of a workspace (Workspaces D-020): one row per workspace and milestone name among the open
 * (planned or in progress) milestones of its live projects, so a milestone created under the same name in several
 * projects appears once.
 *
 * - id: a uuid derived from workspaceId and name (md5 of "<workspaceId>:<name>"), stable across reads.
 * - projectCount: the live projects of the workspace with an open milestone of that name.
 * - startDate / endDate: the earliest start and the latest end of those milestones.
 * - status: 'in_progress' when any of them is in progress, 'planned' otherwise.
 * - issueCount / doneIssueCount: the issues that are not deleted in those milestones, and those of them whose
 *   status is done.
 *
 * Deleted workspaces, projects and milestones have no rows. Milestones stay the source of truth: the route is GET
 * only.
 */
$models['Workspacemilestone'] = array(
    'tableName' => 'workspace_milestones',
    'viewSql' => 'SELECT md5("Project"."workspaceId" || \':\' || "Milestone".name)::uuid::varchar AS id,
                    "Project"."workspaceId" AS "workspaceId",
                    "Milestone".name AS name,
                    COUNT(DISTINCT "Milestone"."projectId")::int AS "projectCount",
                    MIN("Milestone"."startDate") AS "startDate",
                    MAX("Milestone"."endDate") AS "endDate",
                    CASE WHEN BOOL_OR("Milestone".status = \'in_progress\') THEN \'in_progress\'
                        ELSE \'planned\' END AS status,
                    COALESCE(SUM("IssueCount".total), 0)::int AS "issueCount",
                    COALESCE(SUM("IssueCount".done), 0)::int AS "doneIssueCount"
                  FROM milestones AS "Milestone"
                  INNER JOIN projects AS "Project" ON "Project".id = "Milestone"."projectId"
                    AND "Project".deleted = \'0\'
                  INNER JOIN workspaces AS "Workspace" ON "Workspace".id = "Project"."workspaceId"
                    AND "Workspace".deleted = \'0\'
                  LEFT JOIN (SELECT "Issue"."milestoneId" AS "milestoneId",
                        COUNT("Issue".id) AS total,
                        COUNT("Issue".id) FILTER (WHERE "Status".done = \'1\') AS done
                      FROM issues AS "Issue"
                      LEFT JOIN issue_statuses AS "Status" ON "Status".id = "Issue"."statusId"
                      WHERE "Issue".deleted = \'0\'
                      GROUP BY "Issue"."milestoneId") AS "IssueCount"
                    ON "IssueCount"."milestoneId" = "Milestone".id
                  WHERE "Milestone".deleted = \'0\'
                    AND "Milestone".status IN (\'planned\', \'in_progress\')
                  GROUP BY "Project"."workspaceId", "Milestone".name',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKSPACE_MILESTONES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'workspaceId' => array(
            'name' => 'workspaceId',
            'label' => 'LBL_WORKSPACE_MILESTONES_WORKSPACE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_WORKSPACE_MILESTONES_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'projectCount' => array(
            'name' => 'projectCount',
            'label' => 'LBL_WORKSPACE_MILESTONES_PROJECT_COUNT',
            'type' => 'int',
            'null' => false,
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_WORKSPACE_MILESTONES_START_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_WORKSPACE_MILESTONES_END_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_WORKSPACE_MILESTONES_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'issueCount' => array(
            'name' => 'issueCount',
            'label' => 'LBL_WORKSPACE_MILESTONES_ISSUE_COUNT',
            'type' => 'int',
            'null' => false,
        ),
        'doneIssueCount' => array(
            'name' => 'doneIssueCount',
            'label' => 'LBL_WORKSPACE_MILESTONES_DONE_ISSUE_COUNT',
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
        ),
    ),
    // Reading milestones needs workspacemilestone.get plus workspace.get (RBAC D-028): a workspace is not
    // record-scoped, so every row is visible to a caller who holds both.
    'acl' => [
        'groups' => ['Workspace'],
    ],
);

return $models;
