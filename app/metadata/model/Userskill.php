<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userskill'] = array(
    'tableName' => 'user_skills',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_USER_SKILLS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_USER_SKILLS_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_USER_SKILLS_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'proficiencyLevel' => array(
            'name' => 'proficiencyLevel',
            'label' => 'LBL_USER_SKILLS_PROFICIENCY_LEVEL',
            'type' => 'varchar',
            'length' => '25',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_USER_SKILLS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_USER_SKILLS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_USER_SKILLS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_USER_SKILLS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_USER_SKILLS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_USER_SKILLS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_USER_SKILLS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'userId' => 'index',
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
        // 'softDeleteBehavior',
        'modelIdentifierBehavior',
    ),
    'acl' => array(
        'assignment' => array(
            'field' => 'userId',
            'condition' => 'Userskill.userId=:userId:'
        )
    )
);

return $models;
