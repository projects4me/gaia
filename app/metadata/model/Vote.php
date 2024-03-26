<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Vote'] = array(
    'tableName' => 'votes',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_VOTE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_VOTE_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_VOTE_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_VOTE_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_VOTE_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_VOTE_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_VOTE_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'vote' => array(
            'name' => 'vote',
            'label' => 'LBL_VOTE_VOTE',
            'type' => 'bool',
            'null' => false,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_VOTE_RELATED_TO',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_VOTE_RELATED_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'relatedName' => array(
            'name' => 'relatedName',
            'label' => 'LBL_VOTE_RELATED_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'createdBy' => array(
                'primaryKey' => 'createdUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            ),
            'modifiedBy' => array(
                'primaryKey' => 'modifiedUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            )
        ),
        'belongsTo' => array(
            'wiki' => array(
                'primaryKey' => 'relatedId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Wiki',
                'relatedKey' => 'id',
                'condition' => 'Vote.relatedTo = "wiki"'
            ),
            'comment' => array(
                'primaryKey' => 'relatedId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Comment',
                'relatedKey' => 'id',
                'condition' => 'Vote.relatedTo = "comment"'
            ),
            'conversationroom' => array(
                'primaryKey' => 'relatedId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Conversationroom',
                'relatedKey' => 'id',
                'condition' => 'Vote.relatedTo = "conversationroom"'
            )
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior'
    ),
    'customIdentifiers' => []
);

return $models;
