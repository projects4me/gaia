<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Useropenclosedproject'] = array(
    'tableName' => 'user_open_closed_projects',
    'viewSql' => 'SELECT
                MIN(uuid_generate_v4()::varchar) as id,
                SUM(CASE WHEN "Project".done = \'0\' THEN 1 ELSE 0 END) as "openProjects",
                SUM(CASE WHEN "Project".done = \'1\' THEN 1 ELSE 0 END) as "closedProjects",
                "Membership"."userId" as "userId"
                FROM projects AS "Project"
                INNER JOIN memberships AS "Membership" ON "Membership"."projectId" = "Project".id
                GROUP BY "Membership"."userId"',
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
