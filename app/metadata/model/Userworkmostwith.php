<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userworkmostwith'] = array(
    'tableName' => 'user_works_most_with',
    'viewSql' => 'SELECT Membership1.userId as userId, Membership2.userId as id, User.name as name, User.title as title, COUNT(Membership2.id) as occurenceOfUser from projects as Project 
                  left join memberships as Membership1 on Membership1.relatedId = Project.id
                  left join memberships as Membership2 on Membership2.relatedId = Membership1.relatedId  AND Membership2.userId != getModelId()
                  left join users as User on User.id = Membership2.userId 
                  where Membership1.userId = getModelId()
                  GROUP BY Membership2.userId 
                  ORDER BY occurenceOfUser DESC LIMIT 3',
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
        ),
        'occurenceOfUser' => array(
            'name' => 'occurenceOfUser',
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
