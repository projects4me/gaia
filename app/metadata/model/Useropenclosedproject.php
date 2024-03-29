<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Useropenclosedproject'] = array(
    'tableName' => 'user_open_closed_projects',
    'viewSql' => 'SELECT 
                UUID() as id,
                sum(case when Project.done = "0" then 1 else 0 end) as openProjects,
                sum(case when Project.done = "1" then 1 else 0 end) as closedProjects,
                Membership.userId as userId from projects as Project 
                inner join memberships as Membership on Membership.relatedId = Project.id
                GROUP BY Membership.userId;',
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
    'functions' => array()
);

return $models;
