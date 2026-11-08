<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A service (a Talos runtime, Hermes) that signs in with client_credentials
 * and is not a user: its tokens carry no user_id and only its scopes. The
 * secret is only stored hashed, in the oauth_clients row named by clientId
 * (kind `service`). Clients are created by enrollment; the API only lists,
 * reads and enables or disables them (status), and a service rotates its own
 * secret (rotateSecret). See rbac decisions D-062, D-063, D-067.
 */
$models['Serviceclient'] = array(
    'tableName' => 'serviceclients',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_SERVICECLIENTS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_SERVICECLIENTS_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_SERVICECLIENTS_KIND',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_SERVICECLIENTS_STATUS',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
            'default' => 'active',
        ),
        'scopes' => array(
            'name' => 'scopes',
            'label' => 'LBL_SERVICECLIENTS_SCOPES',
            'type' => 'text',
            'null' => true,
        ),
        'clientId' => array(
            'name' => 'clientId',
            'label' => 'LBL_SERVICECLIENTS_CLIENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'lastUsedAt' => array(
            'name' => 'lastUsedAt',
            'label' => 'LBL_SERVICECLIENTS_LAST_USED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_SERVICECLIENTS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_SERVICECLIENTS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_SERVICECLIENTS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_SERVICECLIENTS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_SERVICECLIENTS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_SERVICECLIENTS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_SERVICECLIENTS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    // A service rotates its own secret (D-067): PATCH rotateSecret on its own
    // record, granted only by the serviceclient.rotate scope.
    'acl' => array(
        'service' => array(
            'actions' => array('update'),
            'kinds' => array('runtime', 'hermes'),
            'filter' => 'Serviceclient.clientId = :serviceClientId:',
            'fields' => array('rotateSecret'),
            'scopes' => array('update' => 'serviceclient.rotate'),
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'clientId' => 'unique',
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
        ),
    ),
    'behaviors' => array(
        'serviceClientBehavior',
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),
);

return $models;
