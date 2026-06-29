<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Useropenclosedissue'] = array(
    'tableName' => 'user_open_closed_issues',
    'viewSql' => 'SELECT
                SUM(CASE WHEN "IssueStatuses".done = \'0\' THEN 1 ELSE 0 END) as "openIssues",
                SUM(CASE WHEN "IssueStatuses".done = \'1\' THEN 1 ELSE 0 END) as "closedIssues",
                MIN(uuid_generate_v4()::varchar) as id,
                "User".id as "userId"
                FROM issues AS "Issue"
                LEFT JOIN users AS "User" ON "User".id = "Issue"."createdUser"
                LEFT JOIN issue_statuses AS "IssueStatuses" ON "IssueStatuses".id = "Issue"."statusId"
                GROUP BY "User".id',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
            'identifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'openIssues' => array(
            'name' => 'openIssues',
            'type' => 'int',
            'null' => false,
        ),
        'closedIssues' => array(
            'name' => 'closedIssues',
            'type' => 'int',
            'null' => false,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array()
);

return $models;
