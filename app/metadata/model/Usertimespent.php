<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usertimespent'] = array(
    'tableName' => 'user_time_spent',
    'viewSql' => 'SELECT UUID() as id, 
                  SUM((Timelog.days * 8 * 60) + (Timelog.hours * 60) + Timelog.minutes) as totalMinutes, User.id as userId from users as User 
                  join issues as Issue on User.id = Issue.createdUser
                  join time_logs as Timelog on Timelog.issueId = Issue.id
                  where Timelog.deleted = "0"
                  GROUP BY User.id;',
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
    'functions' => array(),
    'customIdentifiers' => []
);

return $models;
