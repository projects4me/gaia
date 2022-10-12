<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userworkmostwith'] = array(
    'tableName' => 'user_works_most_with',
    'viewSql' => 'SELECT User2.id as id ,User.id as userId, User2.name as name, User2.title as title from users as User left join users as User2 on "1" = checkUserWorkWithOthers(User.id, User2.id)
                  GROUP BY CONCAT(User.id,User2.id);',
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
        'name' => array(
            'name' => 'name',
            'type' => 'varchar',
            'null' => false,
        ),
        'title' => array(
            'name' => 'title',
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
