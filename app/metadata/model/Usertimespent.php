<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usertimespent'] = array(
    'tableName' => 'user_time_spent',
    'viewSql' => 'SELECT UUID() as id, 
                  SUM((tl.days * 8 * 60) + (tl.hours * 60) + tl.minutes) as totalMinutes, u.id as userId from users u 
                  join issues i on u.id = i.createdUser
                  join time_logs tl on tl.issueId = i.id
                  where tl.deleted = "0"
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
