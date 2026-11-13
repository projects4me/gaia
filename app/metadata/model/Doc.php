<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Doc'] = array(
    'tableName' => 'doc',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_DOC_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_DOC_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'title' => array(
            'name' => 'title',
            'label' => 'LBL_DOC_TITLE',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'ownerId' => array(
            'name' => 'ownerId',
            'label' => 'LBL_DOC_OWNER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'locked' => array(
            'name' => 'locked',
            'label' => 'LBL_DOC_LOCKED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_DOC_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_DOC_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_DOC_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_DOC_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_DOC_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_DOC_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_DOC_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'projectId' => 'INDEX',
        'ownerId' => 'INDEX',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'project' => array(
                'primaryKey' => 'projectId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'id'
            ),
            'owner' => array(
                'primaryKey' => 'ownerId',
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
            'content' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Doccontent',
                'relatedKey' => 'docId'
            ),
        ),
        'hasMany' => array(
            'tagged' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Tagged',
                'relatedKey' => 'relatedId',
                'condition' => 'tagged.relatedTo = "doc"'
            ),
        ),
        'hasManyToMany' => array(
            'tag' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Tagged',
                'rhsKey' => 'relatedId',
                'lhsKey' => 'tagId',
                'secondaryModel' => '\\Gaia\\MVC\\Models\\Tag',
                'secondaryKey' => 'id',
                'condition' => 'tagTagged.relatedTo = "doc"'
            )
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
    'acl' => [
        'groups' => ['Project'],
        'groupKeys' => [
            'Project' => 'projectId',
        ],
    ],
);

return $models;
