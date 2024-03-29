<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Useropenclosedissue'] = array(
    'tableName' => 'user_open_closed_issues',
    'viewSql' => 'SELECT 
                sum(case when IssueStatuses.done="0" then 1 else 0 end) as openIssues,
                sum(case when IssueStatuses.done="1" then 1 else 0 end) as closedIssues,
                UUID() as id,
                User.id as userId from issues as Issue
                left join users as User on User.id = Issue.createdUser 
                left join issue_statuses as IssueStatuses on IssueStatuses.id = Issue.statusId
                GROUP BY User.id;',
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
