<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */


$models['Oauthclient'] = array(
   'tableName' => 'oauth_clients',
   'fields' => array(
       'id' => array(
           'name' => 'id',
           'label' => 'LBL_OAUTH_CLIENTS_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'client_id' => array(
           'name' => 'client_id',
           'label' => 'LBL_OAUTH_CLIENTS_CLIENTSID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'client_secret' => array(
           'name' => 'client_secret',
           'label' => 'LBL_OAUTH_CLIENTS_CLIENT_SECRET',
           'type' => 'varchar',
           'length' => '80',
           'null' => false,
       ),
       'redirect_uri' => array(
           'name' => 'redirect_uri',
           'label' => 'LBL_OAUTH_CLIENTS_REDIRECT_URI',
           'type' => 'varchar',
           'length' => '2000',
           'null' => false,
       ),
       'grant_types' => array(
           'name' => 'grant_types',
           'label' => 'LBL_OAUTH_CLIENTS_GRANT_TYPES',
           'type' => 'varchar',
           'length' => '80',
           'null' => true,
       ),
       'scope' => array(
           'name' => 'scope',
           'label' => 'LBL_OAUTH_CLIENTS_SCOPE',
           'type' => 'varchar',
           'length' => '100',
           'null' => true,
       ),
       'user_id' => array(
           'name' => 'user_id',
           'label' => 'LBL_OAUTH_CLIENTS_USER_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => true,
       ),
       // Who signs in with the client: app (people, password grant), agent
       // (an agent user, client_credentials) or service (a Serviceclient,
       // client_credentials without a user). See ClientKind.
       'kind' => array(
           'name' => 'kind',
           'label' => 'LBL_OAUTH_CLIENTS_KIND',
           'type' => 'varchar',
           'length' => '10',
           'null' => false,
           'default' => 'app',
       ),
       // A rotated service secret's previous hash, still accepted until
       // previous_secret_expires (UTC) so a service that crashes mid-rotation
       // is not locked out. See ServiceClientSecrets (D-067).
       'previous_client_secret' => array(
           'name' => 'previous_client_secret',
           'label' => 'LBL_OAUTH_CLIENTS_PREVIOUS_CLIENT_SECRET',
           'type' => 'varchar',
           'length' => '80',
           'null' => true,
       ),
       'previous_secret_expires' => array(
           'name' => 'previous_secret_expires',
           'label' => 'LBL_OAUTH_CLIENTS_PREVIOUS_SECRET_EXPIRES',
           'type' => 'datetime',
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
