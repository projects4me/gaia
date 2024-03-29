<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Wiki'] = array(
    'tableName' => 'wiki',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WIKI_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_WIKI_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
            'fts' => true,
            'linkedTo' => 'id'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WIKI_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WIKI_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_WIKI_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_COMMENTS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_WIKI_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_COMMENTS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WIKI_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_WIKI_STATUS',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
            'fts' => true
        ),
        'locked' => array(
            'name' => 'locked',
            'label' => 'LBL_WIKI_LOCKED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'fts' => true
        ),
        'upvotes' => array(
            'name' => 'upvotes',
            'label' => 'LBL_WIKI_UPVOTES',
            'type' => 'int',
            'length' => '5',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_WIKI_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'markUp' => array(
            'name' => 'markUp',
            'label' => 'LBL_WIKI_MARK_UP',
            'type' => 'text',
            'null' => false,
            'fts' => true
        ),
        'parentId' => array(
            'name' => 'parentId',
            'label' => 'LBL_WIKI_PARENT',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
            'relatedIdentifier' => true
        )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'belongsTo' => array(
            'project' => array(
                'primaryKey' => 'projectId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'id'
            ),
            'parent' => array(
                'primaryKey' => 'parentId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Wiki',
                'relatedKey' => 'id'
            )
        ),
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
            'vote' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Vote',
                'relatedKey' => 'relatedId',
                'condition' => 'vote.relatedTo = "wiki"'
            ),
            'tagged' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Tagged',
                'relatedKey' => 'relatedId',
                'condition' => 'tagged.relatedTo = "wiki"'
            ),
            'files' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Upload',
                'relatedKey' => 'relatedId',
                'condition' => 'files.relatedTo = "wiki"',
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
                'condition' => 'tagged.relatedTo = "wiki"'
            )
        )
    ),
    'behaviors' => array(
        'aclBehavior',
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    )
);

return $models;
