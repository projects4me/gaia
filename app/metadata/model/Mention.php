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
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_MENTIONS_RELATED_TO',
            'type' => 'varchar',
            'length' => '30',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_MENTIONS_RELATED_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
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
    'relationships' => array(
        'hasOne' => array(
          'createdUser' => array(
              'primaryKey' => 'createdUser',
              'relatedModel' => 'User',
              'relatedKey' => 'id'
          ),
          'user' => array(
              'primaryKey' => 'userId',
              'relatedModel' => 'User',
              'relatedKey' => 'id'
          ),
          'comment' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => 'Comment',
              'relatedKey' => 'id',
              'condition' => 'Mentions.relatedTo="Comments"'
          ),
          'activity' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => 'Activity',
              'relatedKey' => 'id',
              'condition' => 'Mentions.relatedTo="Activities"'
          )
        )
    ),
);

return $models;
