<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Dashboard'] = array(
    'tableName' => 'dashboards',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_DASHBOARD_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_DASHBOARD_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_DASHBOARD_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_DASHBOARD_DATE_CREATED',
            'type' => 'datetime',
            'null' => false,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_DASHBOARD_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => false,
        ),
        'widgets' => array(
            'name' => 'widgets',
            'label' => 'LBL_DASHBOARD_WIDGETS',
            'type' => 'text',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_DASHBOARD_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0
        ),
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

    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'softDeleteBehavior'
    ),
    'customIdentifiers' => []
);

return $models;
