<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userecentactivity'] = array(
    'tableName' => 'user_activities',
    'viewSql' => 'SELECT a.id, u.id as userId, u.name as createdUserName, a.relatedTo, a.relatedId, a.type, a.dateCreated, a.relatedActivity, a.relatedActivityId, relatedActivityModule,
                 (select i.projectId from issues i where i.id = a.relatedId AND a.relatedTo = "issue") as projectId 
                from users u inner join activities a on "1" = checkActivityIsLatest(u.id, a.id);',
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
        'relatedTo' => array(
            'name' => 'relatedTo',
            'type' => 'varchar',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'type' => 'varchar',
            'null' => false,
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
        ),
        'relatedActivityId' => array(
            'name' => 'relatedActivityId',
            'type' => 'varchar',
            'null' => false,
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
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array(
        'checkActivityIsLatest' => array(
            'functionName' => 'checkActivityIsLatest',
            'returnType' => 'INT(1)',
            'parameters' => 'userId VARCHAR(36), activityId VARCHAR(36)',
            'statement' => '(SELECT IF(COUNT(t2.id) = 1,1,0) as temp from
                                (select * from 
                                (select a.id from users u left join activities a on a.createdUser = u.id where u.id = userId
                                ORDER BY a.dateCreated
                                DESC LIMIT 5) t
                            where t.id = activityId) t2);'
        )
    )
);

return $models;
