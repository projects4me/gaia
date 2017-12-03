<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Conversationroom'] = array(
   'tableName' => 'conversation_rooms',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_CONVERSATION_ROOMS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'subject' => array(
            'name' => 'subject',
            'label' => 'LBL_CONVERSATION_ROOMS_SUBJECT',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_CONVERSATION_ROOMS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_CONVERSATION_ROOMS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_ISSUE_TYPES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
       'description' => array(
            'name' => 'description',
            'label' => 'LBL_CONVERSATION_ROOMS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_CONVERSATION_ROOMS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_CONVERSATION_ROOMS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_CONVERSATION_ROOMS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_CONVERSATION_ROOMS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'roomType' => array(
            'name' => 'roomType',
            'label' => 'LBL_CONVERSATION_ROOMS_ROOM_TYPE',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_CONVERSATION_ROOMS_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'projectName' => array(
            'name' => 'projectName',
            'label' => 'LBL_CONVERSATION_ROOMS_PROJECT_NAME',
            'type' => 'varchar',
            'length' => '255',
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
    'relationships' => array(
      'hasOne' => array(
          'project' => array(
              'primaryKey' => 'projectId',
              'relatedModel' => 'Project',
              'relatedKey' => 'id',
          ),
          'createdBy' => array(
            'primaryKey' => 'createdUser',
            'relatedModel' => 'User',
            'relatedKey' => 'id'
          ),
          'modifiedBy' => array(
            'primaryKey' => 'modifiedUser',
            'relatedModel' => 'User',
            'relatedKey' => 'id'
          ),
      ),
      'hasMany' => array(
          'comments' => array(
              'primaryKey' => 'id',
              'relatedModel' => 'Comment',
              'relatedKey' => 'relatedId',
              'condition' => 'comments.relatedTo = "conversationrooms"'
          ),
          'votes' => array(
            'primaryKey' => 'id',
            'relatedModel' => 'Vote',
            'relatedKey' => 'relatedId',
            'condition' => 'votes.relatedTo = "conversationrooms"'
          ),
      ),
    ),
);

return $models;
