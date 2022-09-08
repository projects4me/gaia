<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Useropenclosedproject'] = array(
    'tableName' => 'user_open_closed_projects',
    'viewSql' => 'SELECT 
                sum(case when p.done = "0" then 1 else 0 end) as openProjects,
                sum(case when p.done = "1" then 1 else 0 end) as closedProjects,
                m.userId as id from projects p 
                inner join memberships m on m.projectId = p.id
                GROUP BY m.userId',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'openProjects' => array(
            'name' => 'openProjects',
            'type' => 'int',
            'null' => false,
        ),
        'closedProjects' => array(
            'name' => 'closedProjects',
            'type' => 'int',
            'null' => false,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
);

return $models;
