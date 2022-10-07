<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usercollaboration'] = array(
    'tableName' => 'user_collaborations',
    'viewSql' => 'SELECT UUID() as id, FLOOR(POWER(COUNT(c.id), "0.5") * 13) as collaboration, u.id as userId from comments c 
                left join users u on u.id = c.createdUser where CAST(NOW() AS DATE) = CAST(c.dateCreated as DATE)
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
