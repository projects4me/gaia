<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * The content of a doc, kept apart from the Doc row so frequent autosaves do not
 * rewrite the doc metadata. No auditBehavior: the state is large and would be
 * copied into the audit log on every save.
 */
$models['Doccontent'] = array(
    'tableName' => 'doccontent',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_DOCCONTENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'docId' => array(
            'name' => 'docId',
            'label' => 'LBL_DOCCONTENT_DOC',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_DOCCONTENT_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'state' => array(
            'name' => 'state',
            'label' => 'LBL_DOCCONTENT_STATE',
            'type' => 'text',
            'null' => true,
        ),
        'html' => array(
            'name' => 'html',
            'label' => 'LBL_DOCCONTENT_HTML',
            'type' => 'text',
            'null' => true,
        ),
        'plainText' => array(
            'name' => 'plainText',
            'label' => 'LBL_DOCCONTENT_PLAIN_TEXT',
            'type' => 'text',
            'null' => true,
        ),
        'byteSize' => array(
            'name' => 'byteSize',
            'label' => 'LBL_DOCCONTENT_BYTE_SIZE',
            'type' => 'int',
            'null' => false,
            'default' => 0,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_DOCCONTENT_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_DOCCONTENT_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_DOCCONTENT_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_DOCCONTENT_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_DOCCONTENT_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_DOCCONTENT_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_DOCCONTENT_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'docId' => 'unique',
        'projectId' => 'INDEX',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'doc' => array(
                'primaryKey' => 'docId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Doc',
                'relatedKey' => 'id'
            ),
            'project' => array(
                'primaryKey' => 'projectId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
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
    ),
    'behaviors' => array(
        'doccontentBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),
    'acl' => [
        'groups' => ['Project'],
        'groupKeys' => [
            'Project' => 'projectId',
        ],
    ],
);

return $models;
