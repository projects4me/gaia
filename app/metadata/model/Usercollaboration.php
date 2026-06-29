<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usercollaboration'] = array(
    'tableName' => 'user_collaborations',
    'viewSql' => 'SELECT MIN(uuid_generate_v4()::varchar) as id, (FLOOR(POWER(COUNT("Comment".id), 0.5) * 13))::double precision as collaboration, "User".id as "userId"
                FROM comments AS "Comment"
                LEFT JOIN users AS "User" ON "User".id = "Comment"."createdUser"
                WHERE CAST(timezone(\'UTC\', now()) AS DATE) = CAST("Comment"."dateCreated" AS DATE)
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
        'collaboration' => array(
            'name' => 'collaboration',
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
