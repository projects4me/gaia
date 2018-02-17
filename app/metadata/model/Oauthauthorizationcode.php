<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */


$models['Oauthauthorizationcode'] = array(
   'tableName' => 'oauth_authorization_codes',
   'fields' => array(
       'authorization_code' => array(
           'name' => 'authorization_code',
           'label' => 'LBL_OAUTH_AUTHORIZATION_CODES_AUTHORIZATION_CODE',
           'type' => 'varchar',
           'length' => '40',
           'null' => false,
       ),
       'client_id' => array(
           'name' => 'client_id',
           'label' => 'LBL_OAUTH_AUTHORIZATION_CODES_CLIENT_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'user_id' => array(
           'name' => 'user_id',
           'label' => 'LBL_OAUTH_AUTHORIZATION_CODES_USER_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => true,
       ),
       'redirect_uri' => array(
           'name' => 'redirect_uri',
           'label' => 'LBL_OAUTH_AUTHORIZATION_CODES_REDIRECT_URI',
           'type' => 'varchar',
           'length' => '2000',
           'null' => true,
       ),
       'scope' => array(
           'name' => 'scope',
           'label' => 'LBL_OAUTH_AUTHORIZATION_CODES_SCOPE',
           'type' => 'varchar',
           'length' => '2000',
           'null' => true,
       ),
       'expires' => array(
           'name' => 'expires',
           'label' => 'LBL_OAUTH_AUTHORIZATION_CODES_EXPIRES',
           'type' => 'datetime',
           'length' => '40',
           'null' => true,
       ),
    ),
    'indexes' => array(
        'authorization_code' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
);

return $models;
