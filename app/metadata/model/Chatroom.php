<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Chatroom'] = array(
    'tableName' => 'chat_rooms',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_CHAT_ROOMS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'subject' => array(
            'name' => 'subject',
            'label' => 'LBL_CHAT_ROOMS_SUBJECT',
            'type' => 'text',
            'null' => false,
            'fts' => true
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_CHAT_ROOMS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_CHAT_ROOMS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_CHAT_ROOMS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_CHAT_ROOMS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_CHAT_ROOMS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_CHAT_ROOMS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_CHAT_ROOMS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_CHAT_ROOMS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
            'fts' => true
        ),
        'type' => array(
            'name' => 'type',
            'label' => 'LBL_CHAT_ROOMS_TYPE',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
            'fts' => true
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
        'hasMany' => array(
            'comments' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Comment',
                'relatedKey' => 'relatedId',
                'condition' => 'comments.relatedTo = "chatrooms"'
            ),
        ),
        'hasManyToMany' => array(
            'ownedby' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Converser',
                'rhsKey' => 'chatRoomId',
                'lhsKey' => 'userId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\User',
                'secondaryKey' => 'id',
                'condition' => 'Chatroom.createdUser = ownedbyConverser.userId'
            ),
            'conversers' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Converser',
                'rhsKey' => 'chatRoomId',
                'lhsKey' => 'userId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\User',
                'secondaryKey' => 'id',
                'condition' => 'Chatroom.createdUser != conversersConverser.userId'
            ),
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),
);

return $models;
