<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Ideaplanissue'] = array(
    'tableName' => 'idea_plan_issues',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_IDEA_PLAN_ISSUES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'ideaId' => array(
            'name' => 'ideaId',
            'label' => 'LBL_IDEA_PLAN_ISSUES_IDEA',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'ideaplanId' => array(
            'name' => 'ideaplanId',
            'label' => 'LBL_IDEA_PLAN_ISSUES_IDEAPLAN',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'key' => array(
            'name' => 'key',
            'label' => 'LBL_IDEA_PLAN_ISSUES_KEY',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'issueId' => array(
            'name' => 'issueId',
            'label' => 'LBL_IDEA_PLAN_ISSUES_ISSUE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_IDEA_PLAN_ISSUES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_IDEA_PLAN_ISSUES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'ideaId' => 'INDEX',
        'ideaplanId_key' => array(
            'type' => 'unique',
            'columns' => array('ideaplanId', 'key'),
        ),
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'issue' => array(
                'primaryKey' => 'issueId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
                'relatedKey' => 'id'
            ),
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'softDeleteBehavior',
        'ideaReferencesBehavior'
    ),
    'acl' => [
        'groups' => ['Project'],
        'groupKeys' => [
            'Project' => [
                'paths' => [
                    [
                        'relatedModel' => 'Idea',
                        'localKey' => 'ideaId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                ],
            ],
        ],
        // The linked issue may be in another project (e.g. an API issue in
        // the backend project for a frontend idea); the writer must be able
        // to read that project.
        'references' => [
            'issueId' => [
                'relatedModel' => 'Issue',
                'relatedKey' => 'id',
                'projectField' => 'projectId',
            ],
        ],
    ],
);

return $models;
