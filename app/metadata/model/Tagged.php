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
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_TAGGED_RELATED',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_TAGGED_RELATED_TO',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
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
      'hasMany' => array(
          'tag' => array(
              'primaryKey' => 'tagId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Tag',
              'relatedKey' => 'id'
          ),
          'project' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
              'relatedKey' => 'id',
              'condition' => 'Tagged.relatedTo = "project"'
          ),
          'issue' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => '\\Gaia\\MVC\\Models\\Issue',
              'relatedKey' => 'id',
              'condition' => 'Tagged.relatedTo = "issue"'
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
        'aclBehavior',
    ),
    'customIdentifiers' => []
);

return $models;
