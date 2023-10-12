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
            'label' => 'LBL_PERMISSIONS_RESOURCE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'roleId' => array(
            'name' => 'roleId',
            'label' => 'LBL_PERMISSIONS_ROLE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'controllerId' => array(
            'name' => 'controllerId',
            'label' => 'LBL_PERMISSIONS_CONTROLLER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'readF' => array(
            'name' => 'readF',
            'label' => 'LBL_PERMISSIONS_READ_F',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'searchF' => array(
            'name' => 'searchF',
            'label' => 'LBL_PERMISSIONS_SEARCH_F',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'createF' => array(
            'name' => 'createF',
            'label' => 'LBL_PERMISSIONS_CREATE_F',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'updateF' => array(
            'name' => 'updateF',
            'label' => 'LBL_PERMISSIONS_UPDATE_F',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'deleteF' => array(
            'name' => 'deleteF',
            'label' => 'LBL_PERMISSIONS_DELETE_F',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'importF' => array(
            'name' => 'importF',
            'label' => 'LBL_PERMISSIONS_IMPORT_F',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'exportF' => array(
            'name' => 'exportF',
            'label' => 'LBL_PERMISSIONS_EXPORT_F',
            'type' => 'int',
            'length' => '1',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_PERMISSIONS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_PERMISSIONS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'controllerId' => 'index'
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
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
