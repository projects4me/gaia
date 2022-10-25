<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestproject'] = array(
    'tableName' => 'user_latest_projects',
    'viewSql' => 'SELECT Membership.userId, Project.id as id, Project.name as name, Project.description as description, Project.status as status, Membership.lastActivityDate as lastActivityDate, Project.shortCode as shortCode,
                    (select COUNT(Issue.id) from issues as Issue where Issue.projectId = Project.id) as totalIssues,
                    (select COUNT(Issue.id) as totalIssues from issues as Issue left join issue_statuses as IssueStatus on IssueStatus.id = Issue.statusId where Issue.projectId = Project.id AND IssueStatus.done="1") as closedIssues
                    from projects as Project inner join memberships as Membership on Membership.projectId = Project.id AND Membership.userId = getModelId()
                  where Membership.createdUser = getModelId()
                  GROUP BY Project.id
                  ORDER BY Membership.lastActivityDate DESC LIMIT 5;',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'type' => 'varchar',
            'null' => false,
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
