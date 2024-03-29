<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Mention'] = array(
    'tableName' => 'mentions',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_MENTIONS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_MENTIONS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_MENTIONS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_MENTIONS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_MENTIONS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_MENTIONS_RELATED_TO',
            'type' => 'varchar',
            'length' => '30',
            'null' => false,
            'linkedTo' => 'relatedId'
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_MENTIONS_RELATED_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
            'relatedIdentifier' => true
        ),
        'notified' => array(
            'name' => 'notified',
            'label' => 'LBL_MENTIONS_NOTIFIED',
            'type' => 'bool',
            'null' => false
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_MENTIONS_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
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
            'createdUser' => array(
                'primaryKey' => 'createdUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'user' => array(
                'primaryKey' => 'userId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'comment' => array(
                'primaryKey' => 'relatedId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Comment',
                'relatedKey' => 'id',
                'condition' => 'Mentions.relatedTo="Comments"'
            ),
            'activity' => array(
                'primaryKey' => 'relatedId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Activity',
                'relatedKey' => 'id',
                'condition' => 'Mentions.relatedTo="Activities"'
            )
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'createdUserBehavior',
        'softDeleteBehavior'
    )
);

return $models;
