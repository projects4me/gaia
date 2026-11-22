<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Issuelink'] = array(
    'tableName' => 'issuelinks',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_ISSUELINKS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_ISSUELINKS_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'sourceIssueId' => array(
            'name' => 'sourceIssueId',
            'label' => 'LBL_ISSUELINKS_SOURCE_ISSUE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'targetIssueId' => array(
            'name' => 'targetIssueId',
            'label' => 'LBL_ISSUELINKS_TARGET_ISSUE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'type' => array(
            'name' => 'type',
            'label' => 'LBL_ISSUELINKS_TYPE',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'targetProjectId' => array(
            'name' => 'targetProjectId',
            'label' => 'LBL_ISSUELINKS_TARGET_PROJECT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_ISSUELINKS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_ISSUELINKS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_ISSUELINKS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_ISSUELINKS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_ISSUELINKS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_ISSUELINKS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_ISSUELINKS_DELETED',
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
        'targetProjectId' => 'INDEX',
        'sourceIssueId' => 'INDEX',
        'targetIssueId' => 'INDEX',
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
            'targetProject' => array(
                'primaryKey' => 'targetProjectId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'id'
            ),
            'sourceIssue' => array(
                'primaryKey' => 'sourceIssueId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'id'
            ),
            'targetIssue' => array(
                'primaryKey' => 'targetIssueId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
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
        'softDeleteBehavior',
        'issueLinkBehavior'
    ),
    // Scoped by the source issue's project (Workspaces D-021). Both issues
    // named in a write must be in projects the writer can read; the project
    // ids are set from the issues by issueLinkBehavior.
    'acl' => [
        'groups' => ['Project'],
        'groupKeys' => [
            'Project' => 'projectId',
        ],
        'references' => [
            'sourceIssueId' => [
                'relatedModel' => 'Issue',
                'relatedKey' => 'id',
                'projectField' => 'projectId',
            ],
            'targetIssueId' => [
                'relatedModel' => 'Issue',
                'relatedKey' => 'id',
                'projectField' => 'projectId',
            ],
        ],
    ],
);

return $models;
