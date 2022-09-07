<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usertimespent'] = array(
    'tableName' => 'user_time_spent',
    'viewSql' => 'SELECT SUM((tl.days * 8 * 60) + (tl.hours * 60) + tl.minutes) as totalMinutes, u.id as id from users u 
                  join issues i on u.id = i.createdUser 
                  join time_logs tl on tl.issueId = i.id GROUP BY u.name',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'totalMinutes' => array(
            'name' => 'total_minutes',
            'type' => 'varchar',
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
