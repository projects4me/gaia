<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userecentactivity'] = array(
    'tableName' => 'user_activities',
    'viewSql' => 'SELECT "Activity".id, "User".id as "userId", "User".name as "createdUserName", "Activity"."relatedTo", "Activity"."relatedId", "Activity".type, "Activity"."dateCreated", "Activity"."relatedActivity", "Activity"."relatedActivityId", "Activity"."relatedActivityModule", "Issue"."projectId", "Issue"."issueNumber"
                    FROM users AS "User" LEFT JOIN activities AS "Activity" ON "Activity"."createdUser" = getmodelid()
                    LEFT JOIN issues AS "Issue" ON "Issue".id = "Activity"."relatedId" AND "Activity"."relatedTo" = \'issue\' AND "Issue"."createdUser" = getmodelid()
                  WHERE "User".id = getmodelid()
                  ORDER BY "Activity"."dateCreated" DESC LIMIT 5',
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
        'relatedTo' => array(
            'name' => 'relatedTo',
            'type' => 'varchar',
            'null' => false,
            'linkedTo' => 'relatedId'
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'type' => array(
            'name' => 'type',
            'type' => 'varchar',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'type' => 'varchar',
            'null' => false,
        ),
        'relatedActivity' => array(
            'name' => 'relatedActivity',
            'type' => 'varchar',
            'null' => false,
            'linkedTo' => 'relatedActivityId'
        ),
        'relatedActivityId' => array(
            'name' => 'relatedActivityId',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'relatedActivityModule' => array(
            'name' => 'relatedActivityModule',
            'type' => 'varchar',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'issueNumber' => array(
            'name' => 'issueNumber',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'userId'
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array()
);

return $models;
