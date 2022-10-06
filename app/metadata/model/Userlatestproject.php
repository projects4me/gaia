<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestproject'] = array(
    'tableName' => 'user_latest_projects',
    'viewSql' => 'SELECT t2.*, a2.dateCreated as lastActivityDate from 
                    (select * from 
                        (select m.userId, p.id as id, m.createdUser, m.id as membershipId,
                            (select COUNT(i.id) from issues i where i.projectId = m.projectId) as totalIssues,
                            (select COUNT(i.id) as totalIssues from issues i left join issue_statuses is2 on is2.id = i.statusId where i.projectId = m.projectId AND is2.done="1") as closedIssues,
                            p.description, p.status, p.name, p.shortCode
                            from projects p
                            inner join memberships m on "1"  = checkProjectIsLatest(m.userId, p.id) AND m.projectId = p.id
                            ORDER BY m.userId DESC) t1) t2
                inner join activities a2 on a2.relatedId = t2.id AND a2.relatedTo ="project" AND a2.createdUser = t2.userId
                GROUP BY CONCAT (t2.userId, t2.membershipId)',
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
