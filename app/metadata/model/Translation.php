<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * UI translations, one row per locale and label.
 *
 * `locale` is a supported locale from config/translations.php (e.g. en-us) and `path`
 * the dotted ember-intl key Prometheus reads (e.g. views.app.issue.lists.status.new).
 * The rows are served as nested JSON by GET /translations/{locale} (TranslationController),
 * cached per locale in filesystem/translations; translationCacheBehavior drops a locale's
 * cache whenever one of its rows changes.
 */
$models['Translation'] = array(
    'tableName' => 'translations',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_TRANSLATIONS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'locale' => array(
            'name' => 'locale',
            'label' => 'LBL_TRANSLATIONS_LOCALE',
            'type' => 'varchar',
            'length' => '20',
            'null' => false
        ),
        'path' => array(
            'name' => 'path',
            'label' => 'LBL_TRANSLATIONS_PATH',
            'type' => 'varchar',
            'length' => '255',
            'null' => false
        ),
        'value' => array(
            'name' => 'value',
            'label' => 'LBL_TRANSLATIONS_VALUE',
            'type' => 'text',
            'null' => false
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_TRANSLATIONS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_TRANSLATIONS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_TRANSLATIONS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_TRANSLATIONS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_TRANSLATIONS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_TRANSLATIONS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_TRANSLATIONS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'locale_path' => array(
            'type' => 'unique',
            'columns' => array('locale', 'path'),
        ),
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
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior',
        'translationCacheBehavior'
    ),
);

return $models;
