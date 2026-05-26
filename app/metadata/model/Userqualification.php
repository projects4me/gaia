<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userqualification'] = array(
    'tableName' => 'user_qualifications',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_USER_QUALIFICATIONS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_USER_QUALIFICATIONS_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'type' => array(
            'name' => 'type',
            'label' => 'LBL_USER_QUALIFICATIONS_TYPE',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'title' => array(
            'name' => 'title',
            'label' => 'LBL_USER_QUALIFICATIONS_TITLE',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'institution' => array(
            'name' => 'institution',
            'label' => 'LBL_USER_QUALIFICATIONS_INSTITUTION',
            'type' => 'varchar',
            'length' => '255',
            'null' => true,
        ),
        'completionYear' => array(
            'name' => 'completionYear',
            'label' => 'LBL_USER_QUALIFICATIONS_COMPLETION_YEAR',
            'type' => 'int',
            'length' => '4',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_USER_QUALIFICATIONS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_USER_QUALIFICATIONS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_USER_QUALIFICATIONS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_USER_QUALIFICATIONS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_USER_QUALIFICATIONS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_USER_QUALIFICATIONS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_USER_QUALIFICATIONS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'userId' => 'index',
        'type' => 'index',
    ),
    'foreignKeys' => array(),
    'triggers' => array(),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'user' => array(
                'primaryKey' => 'userId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            )
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior',
        'modelIdentifierBehavior',
    ),
    'acl' => array(
        'assignment' => array(
            'field' => 'userId',
            'condition' => 'Userqualification.userId=:userId:'
        )
    )
);

return $models;
