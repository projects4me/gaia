<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * An agent is a users row (users.type = Agent) extended 1:1 by an agents row
 * (Class Table Inheritance). The user carries the identity (name, email,
 * accountStatus, roles, memberships); the agent carries the runtime config.
 */
$models['Agent'] = array(
    'tableName' => 'agents',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_AGENTS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_AGENTS_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_AGENTS_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'purpose' => array(
            'name' => 'purpose',
            'label' => 'LBL_AGENTS_PURPOSE',
            'type' => 'varchar',
            'length' => '255',
            'null' => true,
        ),
        'sponsorId' => array(
            'name' => 'sponsorId',
            'label' => 'LBL_AGENTS_SPONSOR',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'rolePrompt' => array(
            'name' => 'rolePrompt',
            'label' => 'LBL_AGENTS_ROLE_PROMPT',
            'type' => 'text',
            'null' => true,
        ),
        'modelConfig' => array(
            'name' => 'modelConfig',
            'label' => 'LBL_AGENTS_MODEL_CONFIG',
            'type' => 'text',
            'null' => true,
        ),
        'maxTurns' => array(
            'name' => 'maxTurns',
            'label' => 'LBL_AGENTS_MAX_TURNS',
            'type' => 'int',
            'null' => true,
        ),
        'maxRunsPerIssue' => array(
            'name' => 'maxRunsPerIssue',
            'label' => 'LBL_AGENTS_MAX_RUNS_PER_ISSUE',
            'type' => 'int',
            'null' => true,
        ),
        'permissionMode' => array(
            'name' => 'permissionMode',
            'label' => 'LBL_AGENTS_PERMISSION_MODE',
            'type' => 'varchar',
            'length' => '25',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_AGENTS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_AGENTS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_AGENTS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_AGENTS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_AGENTS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_AGENTS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_AGENTS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'userId' => 'unique',
        'sponsorId' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'user' => array(
                'primaryKey' => 'userId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'sponsor' => array(
                'primaryKey' => 'sponsorId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
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
            'toolGrants' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Agenttool',
                'relatedKey' => 'agentId',
            ),
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior',
        'agentConfigBehavior'
    ),
    'acl' => [
        // Record scope by sponsor, declared the same way as Userrole's.
        'assignment' => [
            'field' => 'sponsorId',
            'condition' => 'Agent.sponsorId=:userId:'
        ]
    ],
);

return $models;
