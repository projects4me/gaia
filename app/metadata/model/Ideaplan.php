<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Ideaplan'] = array(
    'tableName' => 'idea_plans',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_IDEA_PLANS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'ideaId' => array(
            'name' => 'ideaId',
            'label' => 'LBL_IDEA_PLANS_IDEA',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'version' => array(
            'name' => 'version',
            'label' => 'LBL_IDEA_PLANS_VERSION',
            'type' => 'int',
            'null' => false,
        ),
        'body' => array(
            'name' => 'body',
            'label' => 'LBL_IDEA_PLANS_BODY',
            'type' => 'text',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_IDEA_PLANS_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
            'default' => 'proposed',
        ),
        'error' => array(
            'name' => 'error',
            'label' => 'LBL_IDEA_PLANS_ERROR',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_IDEA_PLANS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_IDEA_PLANS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_IDEA_PLANS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_IDEA_PLANS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_IDEA_PLANS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_IDEA_PLANS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_IDEA_PLANS_DELETED',
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
        'ideaId_version' => array(
            'type' => 'unique',
            'columns' => array('ideaId', 'version'),
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
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'planVersionBehavior',
        'softDeleteBehavior'
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
    ],
);

return $models;
