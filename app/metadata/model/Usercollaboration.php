<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usercollaboration'] = array(
    'tableName' => 'user_collaborations',
    'viewSql' => 'SELECT UUID() as id, FLOOR(POWER(COUNT(Comment.id), "0.5") * 13) as collaboration, User.id as userId from comments as Comment 
                left join users as User on User.id = Comment.createdUser where CAST(NOW() AS DATE) = CAST(Comment.dateCreated as DATE)
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
