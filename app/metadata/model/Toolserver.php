<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A tool server in the catalog: an MCP server, an OpenAPI service (spec holds
 * its OpenAPI JSON) or the builtin tools of a runtime. kind is mcp, openapi
 * or builtin; location (where it runs) is runtime, ares or external;
 * transport is stdio, http or sse, with endpoint (a URL) or command (stdio).
 * The server owns its tool schemas: PATCH with "refresh": true reloads them
 * with MCP tools/list and stamps schemaRefreshedAt, or refreshError when it
 * fails (see ToolServerRefresher).
 */
$models['Toolserver'] = array(
    'tableName' => 'tool_servers',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_TOOL_SERVERS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_TOOL_SERVERS_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_TOOL_SERVERS_KIND',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
            'default' => 'mcp',
        ),
        'location' => array(
            'name' => 'location',
            'label' => 'LBL_TOOL_SERVERS_LOCATION',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
            'default' => 'external',
        ),
        'transport' => array(
            'name' => 'transport',
            'label' => 'LBL_TOOL_SERVERS_TRANSPORT',
            'type' => 'varchar',
            'length' => '20',
            'null' => true,
        ),
        'endpoint' => array(
            'name' => 'endpoint',
            'label' => 'LBL_TOOL_SERVERS_ENDPOINT',
            'type' => 'varchar',
            'length' => '255',
            'null' => true,
        ),
        'command' => array(
            'name' => 'command',
            'label' => 'LBL_TOOL_SERVERS_COMMAND',
            'type' => 'text',
            'null' => true,
        ),
        'spec' => array(
            'name' => 'spec',
            'label' => 'LBL_TOOL_SERVERS_SPEC',
            'type' => 'text',
            'null' => true,
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_TOOL_SERVERS_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'schemaRefreshedAt' => array(
            'name' => 'schemaRefreshedAt',
            'label' => 'LBL_TOOL_SERVERS_SCHEMA_REFRESHED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'refreshError' => array(
            'name' => 'refreshError',
            'label' => 'LBL_TOOL_SERVERS_REFRESH_ERROR',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_TOOL_SERVERS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_TOOL_SERVERS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_TOOL_SERVERS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_TOOL_SERVERS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_TOOL_SERVERS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_TOOL_SERVERS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_TOOL_SERVERS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'name' => 'index',
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
            'tools' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Tool',
                'relatedKey' => 'toolServerId',
            ),
            'grants' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Agenttool',
                'relatedKey' => 'toolServerId',
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
