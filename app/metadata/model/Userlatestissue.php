<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestissue'] = array(
    'tableName' => 'user_latest_issues',
    'viewSql' => 'SELECT Issue.id as id, Issue.subject as subject, Issue.issueNumber as issueNumber, Issue.status as status, Issue.lastActivityDate as lastActivityDate, Issue.createdUser as userId, Issue.projectId as projectId, Project.shortCode as projectShortCode
                  from issues as Issue 
                  left join projects as Project on Issue.projectId = Project.id
                  where Issue.createdUser = getValueToCompare()
                  GROUP BY Issue.id
                  ORDER BY Issue.lastActivityDate DESC LIMIT 5;',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'subject' => array(
            'name' => 'subject',
            'type' => 'varchar',
            'null' => false,
        ),
        'issueNumber' => array(
            'name' => 'issueNumber',
            'type' => 'varchar',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'type' => 'varchar',
            'null' => false,
        ),
        'projectShortCode' => array(
            'name' => 'projectShortCode',
            'type' => 'varchar',
            'null' => false,
        ),
        'lastActivityDate' => array(
            'name' => 'lastActivityDate',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'type' => 'varchar',
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
