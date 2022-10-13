<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestissue'] = array(
    'tableName' => 'user_latest_issues',
    'viewSql' => 'select i.*,a.createdUser as userId from issues i inner join activities a on a.relatedId = i.id AND a.relatedTo = "issue" AND a.createdUser = i.createdUser
                  where i.createdUser = getValueToCompare()
                  ORDER BY a.dateCreated DESC LIMIT 5;',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
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
        ),
        'status' => array(
            'name' => 'status',
            'type' => 'varchar',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'type' => 'varchar',
            'null' => false,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array(
        'getValueToCompare' => array(
            'functionName' => 'getValueToCompare',
            'returnType' => 'VARCHAR(36) CHARSET utf8',
            'parameters' => '',
            'statement' => 'return @variable'
        )
    )
);

return $models;
