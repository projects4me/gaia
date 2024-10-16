<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Permission'] = array(
   'tableName' => 'permissions',
   'fields' => array(
       'id' => array(
           'name' => 'id',
           'label' => 'LBL_PERMISSIONS_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'resourceId' => array(
           'name' => 'resourceId',
           'label' => 'LBL_PERMISSSIONS_RESOURCE_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'roleId' => array(
           'name' => 'roleId',
           'label' => 'LBL_PERMISSSIONS_ROLE_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       '_read' => array(
           'name' => '_read',
           'label' => 'LBL_PERMISSIONS_READ',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_search' => array(
           'name' => '_search',
           'label' => 'LBL_PERMISSIONS_SEARCH',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_create' => array(
           'name' => '_create',
           'label' => 'LBL_PERMISSIONS_CREATE',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_update' => array(
           'name' => '_update',
           'label' => 'LBL_PERMISSIONS_UPDATE',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_delete' => array(
           'name' => '_delete',
           'label' => 'LBL_PERMISSIONS_DELETE',
           'type' => 'int',
           'length' => '1',
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
        'belongsTo' => array(
            'resources' => array(
                'primaryKey' => 'resourceId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Resource',
                'relatedKey' => 'id',
            ),
            'requesters' => array(
                'primaryKey' => 'roleId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Requester',
                'relatedKey' => 'id',
            )
        ),
    ),
);

return $models;
