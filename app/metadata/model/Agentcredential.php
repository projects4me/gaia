<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A secret issued to an agent for the client_credentials grant: who created
 * it (createdUser), when (dateCreated) and when it was last used. The secret
 * itself is only stored hashed, in the agent's oauth_clients row (clientId).
 * A revoked or replaced secret's row is soft-deleted.
 */
$models['Agentcredential'] = array(
    'tableName' => 'agentcredentials',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_AGENTCREDENTIALS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'agentId' => array(
            'name' => 'agentId',
            'label' => 'LBL_AGENTCREDENTIALS_AGENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'clientId' => array(
            'name' => 'clientId',
            'label' => 'LBL_AGENTCREDENTIALS_CLIENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'lastUsedAt' => array(
            'name' => 'lastUsedAt',
            'label' => 'LBL_AGENTCREDENTIALS_LAST_USED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_AGENTCREDENTIALS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_AGENTCREDENTIALS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_AGENTCREDENTIALS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_AGENTCREDENTIALS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_AGENTCREDENTIALS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_AGENTCREDENTIALS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_AGENTCREDENTIALS_DELETED',
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
        'clientId' => 'index',
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
