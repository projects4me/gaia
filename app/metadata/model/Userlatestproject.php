<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestproject'] = array(
    'tableName' => 'user_latest_projects',
    'viewSql' => 'SELECT "Membership"."userId", "Project".id as id, "Project".name as name, "Project".description as description, "Project".status as status, "Membership"."lastActivityDate" as "lastActivityDate", "Project"."shortCode" as "shortCode",
                    (SELECT COUNT("Issue".id) FROM issues AS "Issue" WHERE "Issue"."projectId" = "Project".id) as "totalIssues",
                    (SELECT COUNT("Issue".id) FROM issues AS "Issue" LEFT JOIN issue_statuses AS "IssueStatus" ON "IssueStatus".id = "Issue"."statusId" WHERE "Issue"."projectId" = "Project".id AND "IssueStatus".done = \'1\') as "closedIssues"
                    FROM projects AS "Project" INNER JOIN memberships AS "Membership" ON "Membership"."projectId" = "Project".id AND "Membership"."userId" = getmodelid()
                  WHERE "Membership"."createdUser" = getmodelid()
                  GROUP BY "Project".id, "Project".name, "Project".description, "Project".status, "Project"."shortCode", "Membership"."userId", "Membership"."lastActivityDate"
                  ORDER BY "Membership"."lastActivityDate" DESC LIMIT 5',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'type' => 'varchar',
            'null' => false,
            'linkedTo' => 'id'
        ),
        'description' => array(
            'name' => 'description',
            'type' => 'varchar',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'type' => 'varchar',
            'null' => false,
        ),
        'lastActivityDate' => array(
            'name' => 'lastActivityDate',
            'type' => 'varchar',
            'null' => false,
        ),
        'shortCode' => array(
            'name' => 'shortCode',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'int',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'closedIssues' => array(
            'name' => 'closedIssues',
            'type' => 'int',
            'null' => false,
        ),
        'totalIssues' => array(
            'name' => 'totalIssues',
            'type' => 'int',
            'null' => false,
        ),

    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array()
);

return $models;
