<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A tool grant: gives an agent (agentId) either a whole tool server
 * (toolServerId, e.g. mcp__gaia) or one tool (toolId), never both. A grant
 * on a runtime tool can narrow it with a permission rule, e.g.
 * Bash(git status:*).
 */
$models['Agenttool'] = array(
    'tableName' => 'agent_tools',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_AGENT_TOOLS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'agentId' => array(
            'name' => 'agentId',
            'label' => 'LBL_AGENT_TOOLS_AGENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'toolServerId' => array(
            'name' => 'toolServerId',
            'label' => 'LBL_AGENT_TOOLS_TOOL_SERVER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'toolId' => array(
            'name' => 'toolId',
            'label' => 'LBL_AGENT_TOOLS_TOOL_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'permissionRule' => array(
            'name' => 'permissionRule',
            'label' => 'LBL_AGENT_TOOLS_PERMISSION_RULE',
            'type' => 'varchar',
            'length' => '255',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_AGENT_TOOLS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_AGENT_TOOLS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_AGENT_TOOLS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_AGENT_TOOLS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_AGENT_TOOLS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_AGENT_TOOLS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_AGENT_TOOLS_DELETED',
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
        'toolServerId' => 'index',
        'toolId' => 'index',
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
                'relatedModel' => '\\Gaia\\MVC\\Models\\Agent',
                'relatedKey' => 'id'
            ),
            'toolServer' => array(
                'primaryKey' => 'toolServerId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Toolserver',
                'relatedKey' => 'id'
            ),
            'tool' => array(
                'primaryKey' => 'toolId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Tool',
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
