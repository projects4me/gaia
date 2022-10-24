<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userecentactivity'] = array(
    'tableName' => 'user_activities',
    'viewSql' => 'SELECT Activity.id, User.id as userId, User.name as createdUserName, Activity.relatedTo, Activity.relatedId, Activity.type, Activity.dateCreated, Activity.relatedActivity, Activity.relatedActivityId, relatedActivityModule, Issue.projectId, Issue.issueNumber
                    from users as User left join activities as Activity on Activity.createdUser = getValueToCompare()
                    left join issues as Issue on Issue.id = Activity.relatedId AND Activity.relatedTo = "issue" AND Issue.createdUser = getValueToCompare() 
                  where User.id = getValueToCompare()
                  ORDER BY Activity.dateCreated DESC LIMIT 5;',
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
        'issueNumber' => array(
            'name' => 'issueNumber',
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
    'functions' => array()
);

return $models;
