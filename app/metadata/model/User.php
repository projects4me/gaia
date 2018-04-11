<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['User'] = array(
    'tableName' => 'users',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_USERS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_USERS_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_USERS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_USERS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_USERS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_USERS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_USERS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_USERS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'username' => array(
            'name' => 'username',
            'label' => 'LBL_USERS_USERNAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'password' => array(
            'name' => 'password',
            'label' => 'LBL_USERS_PASSWORD',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'email' => array(
            'name' => 'email',
            'label' => 'LBL_USERS_EMAIL',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_USERS_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => true,
        ),
        'title' => array(
            'name' => 'title',
            'label' => 'LBL_USERS_TITLE',
            'type' => 'varchar',
            'length' => '100',
            'null' => true,
        ),
        'phone' => array(
            'name' => 'phone',
            'label' => 'LBL_USERS_PHONE',
            'type' => 'varchar',
            'length' => '25',
            'null' => true,
        ),
        'education' => array(
            'name' => 'education',
            'label' => 'LBL_USERS_EDUCATION',
            'type' => 'text',
            'null' => true,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'username' => 'unique',
    ),
    'foreignKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
        'hasOne' => array(
            'dashboard' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Dashboard',
                'relatedKey' => 'userId'
            )
        ),
        'hasMany' => array(
            'tagged' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Tagged',
                'relatedKey' => 'relatedId',
                'condition' => 'tagged.relatedTo = "user"'
            ),
        ),
        'hasManyToMany' => array(
            'skills' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Tagged',
                'rhsKey' => 'relatedId',
                'lhsKey' => 'tagId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\Tag',
                'secondaryKey' => 'id',
                'condition' => 'skillsTagged.relatedTo = "user"'
            )
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
    ),

);

return $models;
