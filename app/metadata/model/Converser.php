<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Converser'] = array(
   'tableName' => 'conversers',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_CONVERSERS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_CONVERSERS_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'chatRoomId' => array(
            'name' => 'chatRoomId',
            'label' => 'LBL_CONVERSERS_CHAT_ROOM',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        )
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
          'chatroom' => array(
              'primaryKey' => 'chatRoomId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Chatroom',
              'relatedKey' => 'id',
          ),
      ),
      'hasMany' => array(
        'users' => array(
          'primaryKey' => 'userId',
          'relatedModel' => '\\Gaia\\MVC\\Models\\User',
          'relatedKey' => 'id',
        ),
      )
    )
);

return $models;
