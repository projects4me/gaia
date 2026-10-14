<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Application settings, one row per setting.
 *
 * Rows are seeded (db/seeds/settings.sql); the API only reads and updates them.
 * `name` is the unique dotted key (e.g. email.smtpHost), `groupName` the section
 * shown in the Settings area. Rows with `secure` = 1 hold an encrypted value that
 * is never returned by the API (see settingBehavior and SettingController).
 */
$models['Setting'] = array(
    'tableName' => 'settings',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_SETTING_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_SETTING_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false
        ),
        'groupName' => array(
            'name' => 'groupName',
            'label' => 'LBL_SETTING_GROUP_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false
        ),
        'value' => array(
            'name' => 'value',
            'label' => 'LBL_SETTING_VALUE',
            'type' => 'text',
            'null' => true
        ),
        'secure' => array(
            'name' => 'secure',
            'label' => 'LBL_SETTING_SECURE',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_SETTING_DATE_CREATED',
            'type' => 'datetime',
            'null' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_SETTING_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_SETTING_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_SETTING_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_SETTING_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_SETTING_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_SETTING_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'name' => 'unique',
        'groupName' => 'INDEX'
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
                'relatedKey' => 'id',
            ),
            'modifiedBy' => array(
                'primaryKey' => 'modifiedUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            )
        )
    ),
    // settingBehavior must stay first: it encrypts secure values before
    // auditBehavior records old/new values, so the audit only holds ciphertext.
    'behaviors' => array(
        'settingBehavior',
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),
);

return $models;
