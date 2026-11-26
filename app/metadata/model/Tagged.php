<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Tagged'] = array(
   'tableName' => 'tagged',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_TAGGED_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_TAGGED_RELATED',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_TAGGED_RELATED_TO',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
            'linkedTo' => 'relatedId'
        ),
        'tagId' => array(
            'name' => 'tagId',
            'label' => 'LBL_TAGGED_TAG',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
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
      'hasOne' => array(
          'doc' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Doc',
              'relatedKey' => 'id',
              'condition' => 'Tagged.relatedTo = "doc"'
          ),
          'tag' => array(
              'primaryKey' => 'tagId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Tag',
              'relatedKey' => 'id'
          ),
          'issue' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
              'relatedKey' => 'id',
              'condition' => 'Tagged.relatedTo = "issue"'
          ),
          'conversationroom' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Conversationroom',
              'relatedKey' => 'id',
              'condition' => 'Tagged.relatedTo = "conversationroom"'
          ),
          'wiki' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Wiki',
              'relatedKey' => 'id',
              'condition' => 'Tagged.relatedTo = "wiki"'
          ),
        ),
    ),
    'behaviors' => array(
        'taggedBehavior'
    ),
    // Scoped by the tagged record's project (Tags D-001): a tagging is visible
    // and writable only where the record is. Other relatedTo values match no
    // path, and taggedBehavior refuses them.
    'acl' => [
        'groups' => ['Project'],
        'groupKeys' => [
            'Project' => [
                'paths' => [
                    [
                        'when' => ['relatedTo' => 'issue'],
                        'relatedModel' => 'Issue',
                        'localKey' => 'relatedId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                    [
                        'when' => ['relatedTo' => 'conversationroom'],
                        'relatedModel' => 'Conversationroom',
                        'localKey' => 'relatedId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                    [
                        'when' => ['relatedTo' => 'wiki'],
                        'relatedModel' => 'Wiki',
                        'localKey' => 'relatedId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                    [
                        'when' => ['relatedTo' => 'doc'],
                        'relatedModel' => 'Doc',
                        'localKey' => 'relatedId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                ],
            ],
        ],
    ],
);

return $models;
