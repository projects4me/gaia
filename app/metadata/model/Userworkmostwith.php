<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userworkmostwith'] = array(
    'tableName' => 'user_works_most_with',
    'viewSql' => 'SELECT "Membership1"."userId" as "userId", "Membership2"."userId" as id, "User".name as name, "User".title as title, COUNT("Membership2".id) as "occurenceOfUser"
                  FROM projects AS "Project"
                  LEFT JOIN memberships AS "Membership1" ON "Membership1"."relatedId" = "Project".id
                  LEFT JOIN memberships AS "Membership2" ON "Membership2"."relatedId" = "Membership1"."relatedId" AND "Membership2"."userId" != getmodelid()
                  LEFT JOIN users AS "User" ON "User".id = "Membership2"."userId"
                  WHERE "Membership1"."userId" = getmodelid()
                  GROUP BY "Membership1"."userId", "Membership2"."userId", "User".name, "User".title
                  ORDER BY "occurenceOfUser" DESC LIMIT 3',
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
        'name' => array(
            'name' => 'name',
            'type' => 'varchar',
            'null' => false,
            'linkedTo' => 'userId'
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
