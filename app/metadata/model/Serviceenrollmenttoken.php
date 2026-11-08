<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A single-use token an admin issues so a deployed service can enroll without
 * waiting for approval: a Serviceenrollment request that carries a valid,
 * unused, unexpired token of its kind is approved at once and the token is
 * marked used. The token is returned once, on creation, and stored only as
 * tokenHash. Deleting a token revokes it. See rbac decision D-070.
 */
$models['Serviceenrollmenttoken'] = array(
    'tableName' => 'serviceenrollmenttokens',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_KIND',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'tokenHash' => array(
            'name' => 'tokenHash',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_TOKEN_HASH',
            'type' => 'varchar',
            'length' => '64',
            'null' => false,
            'secure' => true,
        ),
        'expiresAt' => array(
            'name' => 'expiresAt',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_EXPIRES_AT',
            'type' => 'datetime',
            'null' => false,
        ),
        'usedAt' => array(
            'name' => 'usedAt',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_USED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_SERVICEENROLLMENTTOKENS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'tokenHash' => 'index',
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
        'serviceEnrollmentTokenBehavior',
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),
);

return $models;
