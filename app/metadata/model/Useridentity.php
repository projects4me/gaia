<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A public identity of a user (human or agent) on an external provider: git,
 * github, gitlab, slack, ssh and so on. Only public data is stored: handle,
 * email and a public key fingerprint. Tokens and private keys never are.
 * Provider and handle are not unique across users: two agents may share one
 * identity (e.g. a shared GitHub account).
 */
$models['Useridentity'] = array(
    'tableName' => 'user_identities',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_USER_IDENTITIES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_USER_IDENTITIES_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'provider' => array(
            'name' => 'provider',
            'label' => 'LBL_USER_IDENTITIES_PROVIDER',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'handle' => array(
            'name' => 'handle',
            'label' => 'LBL_USER_IDENTITIES_HANDLE',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'email' => array(
            'name' => 'email',
            'label' => 'LBL_USER_IDENTITIES_EMAIL',
            'type' => 'varchar',
            'length' => '255',
            'null' => true,
        ),
        'publicKeyFingerprint' => array(
            'name' => 'publicKeyFingerprint',
            'label' => 'LBL_USER_IDENTITIES_PUBLIC_KEY_FINGERPRINT',
            'type' => 'varchar',
            'length' => '255',
            'null' => true,
        ),
        'verified' => array(
            'name' => 'verified',
            'label' => 'LBL_USER_IDENTITIES_VERIFIED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_USER_IDENTITIES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_USER_IDENTITIES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_USER_IDENTITIES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_USER_IDENTITIES_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_USER_IDENTITIES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_USER_IDENTITIES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_USER_IDENTITIES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'userId' => 'index',
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
