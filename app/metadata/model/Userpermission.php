<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userpermission'] = array(
    'tableName' => 'user_permissions',
    'viewSql' => 'SELECT Permission.id as id, Membership.userId as userId, Resource2.entity, MAX(Permission._read) as _read, MAX(Permission.`_search`) as _search, MAX(Permission.`_create`) as _create, MAX(Permission.`_update`) as _update, MAX(Permission.`_delete`) as _delete, MAX(Permission.`_import`) as _import, MAX(Permission.`_export`) as _export 
                    FROM resources Resource1
                    left join resources Resource2 on Resource2.lft <= Resource1.lft AND Resource2.rht >= Resource1.rht
                    left join permissions Permission on Permission.resourceId = Resource2.id
                    inner join memberships Membership on Membership.roleId = Permission.roleId AND Membership.userId = getModelId()
                    GROUP BY Resource2.id
                    UNION ALL
                    SELECT Permission.id as id, Aclcontroller.entityId as userId, Resource2.entity, MAX(Permission._read) as _read, MAX(Permission.`_search`) as _search, MAX(Permission.`_create`) as _create, MAX(Permission.`_update`) as _update, MAX(Permission.`_delete`) as _delete, MAX(Permission.`_import`) as _import, MAX(Permission.`_export`) as _export 
                    FROM resources Resource1
                    left join resources Resource2 on Resource2.lft <= Resource1.lft AND Resource2.rht >= Resource1.rht
                    left join permissions Permission on Permission.resourceId = Resource2.id
                    inner join acl_controllers Aclcontroller on Aclcontroller.id = Permission.controllerId AND Aclcontroller.entityId = getModelId()
                    GROUP BY Resource2.id;',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
        ),
        'entity' => array(
            'name' => 'entity',
            'type' => 'varchar',
            'null' => false,
        ),
        '_read' => array(
            'name' => '_read',
            'type' => 'varchar',
            'null' => false,
        ),
        '_search' => array(
            'name' => '_search',
            'type' => 'varchar',
            'null' => false,
        ),
        '_create' => array(
            'name' => '_create',
            'type' => 'varchar',
            'null' => false,
        ),
        '_update' => array(
            'name' => '_update',
            'type' => 'varchar',
            'null' => false,
        ),
        '_delete' => array(
            'name' => '_delete',
            'type' => 'varchar',
            'null' => false,
        ),
        '_import' => array(
            'name' => '_import',
            'type' => 'varchar',
            'null' => false,
        ),
        '_export' => array(
            'name' => '_export',
            'type' => 'varchar',
            'null' => false,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array()
);

return $models;
