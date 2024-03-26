<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Oauthaccesstoken'] = array(
   'tableName' => 'oauth_access_tokens',
   'fields' => array(
       'access_token' => array(
           'name' => 'access_token',
           'label' => 'LBL_OAUTH_ACCESS_TOKENS_ACCESS_TOKEN',
           'type' => 'varchar',
           'length' => '40',
           'null' => false,
       ),
       'client_id' => array(
           'name' => 'client_id',
           'label' => 'LBL_OAUTH_ACCESS_TOKENS_CLIENT_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'user_id' => array(
           'name' => 'user_id',
           'label' => 'LBL_OAUTH_ACCESS_TOKENS_USER_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => true,
       ),
       'expires' => array(
           'name' => 'expires',
           'label' => 'LBL_OAUTH_ACCESS_TOKENS_EXPIRES',
           'type' => 'datetime',
           'length' => '6',
           'null' => true,
       ),
       'scope' => array(
           'name' => 'scope',
           'label' => 'LBL_OAUTH_ACCESS_TOKENS_SCOPE',
           'type' => 'varchar',
           'length' => '2000',
           'null' => true,
       ),
    ),
    'indexes' => array(
        'access_token' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'functions' => array(),
    'customIdentifiers' => []
);

return $models;
