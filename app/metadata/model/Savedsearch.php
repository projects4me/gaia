<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Savedsearch'] = array(
    'tableName' => 'saved_searches',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_SAVED_SEARCHES_ID',
            'type' => 'char',
            'length' => '36',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_SAVED_SEARCHES_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_SAVED_SEARCHES_DATE_CREATED',
            'type' => 'datetime',
            'null' => false,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_SAVED_SEARCHES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_SAVED_SEARCHES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'public' => array(
            'name' => 'public',
            'label' => 'LBL_SAVED_SEARCHES_PUBLIC',
            'type' => 'bool',
            'null' => true,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_SAVED_SEARCHES_RELATED_TO',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
        ),
        'searchquery' => array(
            'name' => 'searchquery',
            'label' => 'LBL_SAVED_SEARCHES_SEARCH_QUERY',
            'type' => 'text',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_SAVED_SEARCHES_PROJECT_ID',
            'type' => 'char',
            'length' => '36',
            'null' => true
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
        'hasOne' => array(
            'createdBy' => array(
                'primaryKey' => 'createdUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            )
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
    ),

);

return $models;
