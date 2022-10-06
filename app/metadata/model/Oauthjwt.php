<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Oauthjwt'] = array(
   'tableName' => 'oauth_jwt',
   'fields' => array(
       'client_id' => array(
           'name' => 'client_id',
           'label' => 'LBL_OAUTH_JWT_CLIENT_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'subject' => array(
           'name' => 'subject',
           'label' => 'LBL_OAUTH_JWT_SUBJECT',
           'type' => 'varchar',
           'length' => '80',
           'null' => true,
       ),
       'public_key' => array(
           'name' => 'public_key',
           'label' => 'LBL_OAUTH_JWT_CLIENT_PUBLIC_KEY',
           'type' => 'varchar',
           'length' => '2000',
           'null' => true,
       ),
    ),
    'indexes' => array(
        'client_id' => 'primary',
    ),
    'foriegnKeys' => array(
       
    ) ,
    'triggers' => array(
        
    ),
    'functions' => array()
);

return $models;