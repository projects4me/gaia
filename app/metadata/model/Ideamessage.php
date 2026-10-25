<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Ideamessage'] = array(
    'tableName' => 'idea_messages',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_IDEA_MESSAGES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'ideaId' => array(
            'name' => 'ideaId',
            'label' => 'LBL_IDEA_MESSAGES_IDEA',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'body' => array(
            'name' => 'body',
            'label' => 'LBL_IDEA_MESSAGES_BODY',
            'type' => 'text',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_IDEA_MESSAGES_KIND',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
            'default' => 'message',
        ),
        'steps' => array(
            'name' => 'steps',
            'label' => 'LBL_IDEA_MESSAGES_STEPS',
            'type' => 'text',
            'null' => true,
        ),
        'screens' => array(
            'name' => 'screens',
            'label' => 'LBL_IDEA_MESSAGES_SCREENS',
            'type' => 'text',
            'null' => true,
        ),
        'ideaplanId' => array(
            'name' => 'ideaplanId',
            'label' => 'LBL_IDEA_MESSAGES_IDEAPLAN',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'costUsd' => array(
            'name' => 'costUsd',
            'label' => 'LBL_IDEA_MESSAGES_COST_USD',
            'type' => 'float',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_IDEA_MESSAGES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_IDEA_MESSAGES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_IDEA_MESSAGES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_IDEA_MESSAGES_DELETED',
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
        ),
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'createdUserBehavior',
        'softDeleteBehavior',
        'ideaScreensBehavior',
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
    ],
);

return $models;
