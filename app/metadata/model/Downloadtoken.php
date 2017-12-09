<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Downloadtoken'] = array(
    'tableName' => 'download_tokens',
    'fields' => array(
        'downloadToken' => array(
            'name' => 'downloadToken',
            'label' => 'LBL_DOWNLOAD_TOKEN_DOWNLOAD_TOKEN',
            'type' => 'char',
            'length' => '36',
            'null' => false,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_DOWNLOAD_TOKEN_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_DOWNLOAD_TOKEN_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_DOWNLOAD_TOKEN_RELATED_TO',
            'type' => 'varchar',
            'length' => '10',
            'null' => true,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_DOWNLOAD_TOKEN_RELATED_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'uploadId' => array(
            'name' => 'uploadId',
            'label' => 'LBL_DOWNLOAD_TOKEN_UPLOAD_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'expires' => array(
            'name' => 'expires',
            'label' => 'LBL_DOWNLOAD_TOKEN_EXPIRES',
            'type' => 'datetime',
            'length' => '36',
            'null' => true,
        )
    ),
    'indexes' => array(
        'downloadToken' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
    'relationships' => array(
        'hasOne' => array(
            'issueUpload' => array(
                'primaryKey' => 'uploadId',
                'relatedModel' => 'Upload',
                'relatedKey' => 'id',
                'condition' => 'Downloadtoken.relatedTo = "issue"'
            )
        )
    )
);

return $models;
