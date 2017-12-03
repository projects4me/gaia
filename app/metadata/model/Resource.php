<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Resource'] = array(
   'tableName' => 'resources',
   'fields' => array(
       'id' => array(
           'name' => 'id',
           'label' => 'LBL_RESOURCES_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'parentId' => array(
           'name' => 'parentId',
           'label' => 'LBL_RESOURCES_PARENT_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => true,
       ),
       'entity' => array(
           'name' => 'entity',
           'label' => 'LBL_RESOURCES_ENTITY',
           'type' => 'varchar',
           'length' => '50',
           'null' => false,
       ),
       'lft' => array(
         'name' => 'lft',
         'label' => 'LBL_RESOURCES_LEFT',
         'type' => 'int',
         'length' => '11',
         'null' => false,
       ),
       'rht' => array(
         'name' => 'rht',
         'label' => 'LBL_RESOURCES_RIGHT',
         'type' => 'int',
         'length' => '11',
         'null' => false,
       )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
        'hasMany' => array(
            'permissions' => array(
                'primaryKey' => 'id',
                'relatedModel' => 'Permission',
                'relatedKey' => 'resourceId',
            ),
          ),
          'belongsTo' => array(
            'child' => array(
                'primaryKey' => 'parentId',
                'relatedModel' => 'Resource',
                'relatedKey' => 'id',
                'conditionExclusive' => 'Resource.lft BETWEEN child.lft AND child.rht',
                'relType' => 'INNER'
            ),
        ),
    ),
);

return $models;
