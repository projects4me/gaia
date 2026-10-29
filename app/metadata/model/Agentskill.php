<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Agentskill'] = array(
    'tableName' => 'agent_skills',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_AGENT_SKILLS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'agentId' => array(
            'name' => 'agentId',
            'label' => 'LBL_AGENT_SKILLS_AGENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'skillId' => array(
            'name' => 'skillId',
            'label' => 'LBL_AGENT_SKILLS_SKILL_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_AGENT_SKILLS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_AGENT_SKILLS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_AGENT_SKILLS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_AGENT_SKILLS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_AGENT_SKILLS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_AGENT_SKILLS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_AGENT_SKILLS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'agentId' => 'index',
        'skillId' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'agent' => array(
                'primaryKey' => 'agentId',
                'relatedModel' => '\Gaia\MVC\Models\Agent',
                'relatedKey' => 'id'
            ),
            'skill' => array(
                'primaryKey' => 'skillId',
                'relatedModel' => '\Gaia\MVC\Models\Skill',
                'relatedKey' => 'id'
            ),
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
