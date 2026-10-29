<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Skill'] = array(
    'tableName' => 'skills',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_SKILLS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_SKILLS_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_SKILLS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'content' => array(
            'name' => 'content',
            'label' => 'LBL_SKILLS_CONTENT',
            'type' => 'text',
            'null' => true,
        ),
        'version' => array(
            'name' => 'version',
            'label' => 'LBL_SKILLS_VERSION',
            'type' => 'varchar',
            'length' => '25',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_SKILLS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_SKILLS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_SKILLS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_SKILLS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_SKILLS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_SKILLS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_SKILLS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'createdBy' => array(
                'primaryKey' => 'createdUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'modifiedBy' => array(
                'primaryKey' => 'modifiedUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
        ),
        'hasMany' => array(
            'files' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\Gaia\MVC\Models\Upload',
                'relatedKey' => 'relatedId',
                'condition' => 'files.relatedTo = "skill"',
            ),
            'agentSkills' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\Gaia\MVC\Models\Agentskill',
                'relatedKey' => 'skillId',
            ),
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),
);

return $models;
