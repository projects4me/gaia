<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * A service's request for a service client (device-authorization style, RFC
 * 8628). The service creates it anonymously and shows its userCode; an admin
 * approves or denies the matching request (PATCH status); the service polls it
 * anonymously with its poll token (stored only as pollTokenHash) and, once it
 * is approved, receives the client id and secret of the Serviceclient it
 * creates, once. See rbac decision D-066.
 */
$models['Serviceenrollment'] = array(
    'tableName' => 'serviceenrollments',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_SERVICEENROLLMENTS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_SERVICEENROLLMENTS_NAME',
            'type' => 'varchar',
            'length' => '100',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_SERVICEENROLLMENTS_KIND',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_SERVICEENROLLMENTS_STATUS',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
            'default' => 'pending',
        ),
        'userCode' => array(
            'name' => 'userCode',
            'label' => 'LBL_SERVICEENROLLMENTS_USER_CODE',
            'type' => 'varchar',
            'length' => '10',
            'null' => false,
        ),
        'pollTokenHash' => array(
            'name' => 'pollTokenHash',
            'label' => 'LBL_SERVICEENROLLMENTS_POLL_TOKEN_HASH',
            'type' => 'varchar',
            'length' => '64',
            'null' => false,
            'secure' => true,
        ),
        'expiresAt' => array(
            'name' => 'expiresAt',
            'label' => 'LBL_SERVICEENROLLMENTS_EXPIRES_AT',
            'type' => 'datetime',
            'null' => false,
        ),
        'approvedBy' => array(
            'name' => 'approvedBy',
            'label' => 'LBL_SERVICEENROLLMENTS_APPROVED_BY',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
            'relatedIdentifier' => true
        ),
        'serviceclientId' => array(
            'name' => 'serviceclientId',
            'label' => 'LBL_SERVICEENROLLMENTS_SERVICECLIENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
            'relatedIdentifier' => true
        ),
        'requesterIp' => array(
            'name' => 'requesterIp',
            'label' => 'LBL_SERVICEENROLLMENTS_REQUESTER_IP',
            'type' => 'varchar',
            'length' => '45',
            'null' => false,
            'secure' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_SERVICEENROLLMENTS_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_SERVICEENROLLMENTS_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'createdUser'
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_SERVICEENROLLMENTS_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_SERVICEENROLLMENTS_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
            'linkedTo' => 'modifiedUser'
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_SERVICEENROLLMENTS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_SERVICEENROLLMENTS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_SERVICEENROLLMENTS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'userCode' => 'index',
        'requesterIp' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'approver' => array(
                'primaryKey' => 'approvedBy',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'serviceclient' => array(
                'primaryKey' => 'serviceclientId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Serviceclient',
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
        'serviceEnrollmentBehavior',
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    ),
);

return $models;
