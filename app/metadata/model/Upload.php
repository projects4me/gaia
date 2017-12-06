<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Upload'] = array(
   'tableName' => 'uploads',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_UPLOAD_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
       'name' => array(
           'name' => 'name',
           'label' => 'LBL_UPLOAD_NAME',
           'type' => 'varchar',
           'length' => '255',
           'null' => false,
       ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_UPLOAD_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_UPLOAD_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_UPLOAD_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_UPLOAD_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_UPLOAD_STATUS',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_UPLOAD_RELATED',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_UPLOAD_RELATED_TO',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'fileType' => array(
            'name' => 'fileType',
            'label' => 'LBL_UPLOAD_FILE_TYPE',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'fileSize' => array(
            'name' => 'fileSize',
            'label' => 'LBL_UPLOAD_FILE_SIZE',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'fileMime' => array(
            'name' => 'fileMime',
            'label' => 'LBL_UPLOAD_FILE_MIME',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'filePath' => array(
            'name' => 'filePath',
            'label' => 'LBL_UPLOAD_FILE_PATH',
            'type' => 'text',
            'null' => true,
        ),
        'fileDestination' => array(
            'name' => 'fileDestination',
            'label' => 'LBL_UPLOAD_FILE_DESTINATION',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
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
      'hasOne' => array(
        'createdBy' => array(
          'primaryKey' => 'createdUser',
          'relatedModel' => 'User',
          'relatedKey' => 'id'
        ),
        'modifiedBy' => array(
          'primaryKey' => 'modifiedUser',
          'relatedModel' => 'User',
          'relatedKey' => 'id'
        ),
      ),
    ),
    'behaviors' => array(
        //'aclBehavior',
    ),
);

return $models;
