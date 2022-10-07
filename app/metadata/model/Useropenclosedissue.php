<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Useropenclosedissue'] = array(
    'tableName' => 'user_open_closed_issues',
    'viewSql' => 'SELECT 
                sum(case when is2.done="0" then 1 else 0 end) as openIssues,
                sum(case when is2.done="1" then 1 else 0 end) as closedIssues,
                UUID() as id,
                u.id as userId from issues i
                left join users u on u.id = i.createdUser 
                left join issue_statuses is2 on is2.id = i.statusId
                GROUP BY u.id;',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
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
