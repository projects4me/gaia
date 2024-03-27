<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Oauthscope'] = array(
   'tableName' => 'oauth_scopes',
   'fields' => array(
       'id' => array(
           'name' => 'id',
           'label' => 'LBL_OAUTH_SCOPES_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'scope' => array(
           'name' => 'scope',
           'label' => 'LBL_OAUTH_SCOPES_SCOPE',
           'type' => 'text',
           'length' => '255',
           'null' => false,
       ),
       'is_default' => array(
           'name' => 'is_default',
           'label' => 'LBL_OAUTH_SCOPES_IS_DEFAULT',
           'type' => 'bool',
           'length' => '1',
           'null' => true,
           'default' => '0'
       ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'functions' => array()
);

return $models;
