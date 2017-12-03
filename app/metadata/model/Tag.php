<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Tag'] = array(
   'tableName' => 'tags',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_TAG_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'tag' => array(
             'name' => 'tag',
             'label' => 'LBL_TAG_TAG',
             'type' => 'varchar',
             'length' => '100',
             'null' => true,
         ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_TAG_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_TAG_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_TAG_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_COMMENTS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_COMMENTS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_COMMENTS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_COMMENTS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'tag' => 'unique'
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
      'hasOne' => array(
        'createdBy' => array(
          'primaryKey' => 'createdUser',
          'relatedModel' => 'User',
          'relatedKey' => 'id',
        ),
        'modifiedBy' => array(
          'primaryKey' => 'modifiedUser',
          'relatedModel' => 'User',
          'relatedKey' => 'id',
        )
      ),
      'hasManyToMany' => array(
        'wiki' => array(
          'primaryKey' => 'id',
          'relatedModel' => 'Tagged',
          'rhsKey' => 'tagId',
          'lhsKey' => 'relatedId',
          'secondaryModel' => 'Wiki',
          'secondaryKey' => 'id',
          'condition' => 'Tagged.relatedTo = "wiki"'
        ),
        'issue' => array(
          'primaryKey' => 'id',
          'relatedModel' => 'Tagged',
          'rhsKey' => 'tagId',
          'lhsKey' => 'relatedId',
          'secondaryModel' => 'Issue',
          'secondaryKey' => 'id',
          'condition' => 'Tagged.relatedTo = "issue"'
        ),
        'project' => array(
          'primaryKey' => 'id',
          'relatedModel' => 'Tagged',
          'rhsKey' => 'tagId',
          'lhsKey' => 'relatedId',
          'secondaryModel' => 'Project',
          'secondaryKey' => 'id',
          'condition' => 'Tagged.relatedTo = "project"'
        )
      )
    ),
);

return $models;
