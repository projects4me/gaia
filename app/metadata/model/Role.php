<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Role'] = array(
   'tableName' => 'roles',
   'fields' => array(
       'id' => array(
           'name' => 'id',
           'label' => 'LBL_ROLES_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'name' => array(
           'name' => 'name',
           'label' => 'LBL_ROLES_NAME',
           'type' => 'varchar',
           'length' => '50',
           'null' => false,
       ),
       'dateCreated' => array(
           'name' => 'dateCreated',
           'label' => 'LBL_ROLES_DATE_CREATED',
           'type' => 'datetime',
           'null' => true,
       ),
       'dateModified' => array(
           'name' => 'dateModified',
           'label' => 'LBL_ROLES_DATE_MODIFIED',
           'type' => 'datetime',
           'null' => true,
       ),
       'deleted' => array(
           'name' => 'deleted',
           'label' => 'LBL_ROLES_DELETED',
           'type' => 'bool',
           'length' => '1',
           'null' => false,
       ),
      'description' => array(
           'name' => 'description',
           'label' => 'LBL_ROLES_DESCRIPTION',
           'type' => 'text',
           'null' => true,
       ),
       'createdUser' => array(
           'name' => 'createdUser',
           'label' => 'LBL_ROLES_CREATED_USER',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'modifiedUser' => array(
           'name' => 'modifiedUser',
           'label' => 'LBL_ROLES_MODIFIED_USER',
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
    'relationships' => array(
        'hasMany' => array(
            'permissions' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Permission',
                'relatedKey' => 'roleId',
            ),
        ),
    ),
);

return $models;
