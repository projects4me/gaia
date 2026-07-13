<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usertimespent'] = array(
    'tableName' => 'user_time_spent',
    'viewSql' => 'SELECT MIN(uuid_generate_v4()::varchar) as id,
                  SUM(("Timelog".days * 8 * 60) + ("Timelog".hours * 60) + "Timelog".minutes) as "totalMinutes", "User".id as "userId"
                  FROM users AS "User"
                  JOIN issues AS "Issue" ON "User".id = "Issue"."createdUser"
                  JOIN time_logs AS "Timelog" ON "Timelog"."issueId" = "Issue".id
                  WHERE "Timelog".deleted = \'0\'
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
        'totalMinutes' => array(
            'name' => 'totalMinutes',
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
