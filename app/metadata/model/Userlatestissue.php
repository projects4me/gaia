<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestissue'] = array(
    'tableName' => 'user_latest_issues',
    'viewSql' => 'SELECT "Issue".id as id, "Issue".subject as subject, "Issue"."issueNumber" as "issueNumber", "Issue".status as status, "Issue"."lastActivityDate" as "lastActivityDate", "Issue"."createdUser" as "userId", "Issue"."projectId" as "projectId", "Project"."shortCode" as "projectShortCode"
                  FROM issues AS "Issue"
                  LEFT JOIN projects AS "Project" ON "Issue"."projectId" = "Project".id
                  WHERE "Issue"."createdUser" = getmodelid()
                  GROUP BY "Issue".id, "Issue".subject, "Issue"."issueNumber", "Issue".status, "Issue"."lastActivityDate", "Issue"."createdUser", "Issue"."projectId", "Project"."shortCode"
                  ORDER BY "Issue"."lastActivityDate" DESC LIMIT 5',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
            'identifier' => true
        ),
        'subject' => array(
            'name' => 'subject',
            'type' => 'varchar',
            'null' => false,
        ),
        'issueNumber' => array(
            'name' => 'issueNumber',
            'type' => 'varchar',
            'null' => false,
            'linkedTo' => 'id'
        ),
        'status' => array(
            'name' => 'status',
            'type' => 'varchar',
            'null' => false,
        ),
        'projectShortCode' => array(
            'name' => 'projectShortCode',
            'type' => 'varchar',
            'null' => false,
            'linkedTo' => 'projectId'
        ),
        'lastActivityDate' => array(
            'name' => 'lastActivityDate',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'projectId' => array(
            'name' => 'projectId',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
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
