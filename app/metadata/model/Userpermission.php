<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userpermission'] = array(
    'tableName' => 'user_permissions',
    'viewSql' => 'SELECT Permission.id as id, Membership.userId as userId, Resource2.entity, MAX(Permission.readF) as readF, MAX(Permission.`searchF`) as searchF, MAX(Permission.`createF`) as createF, MAX(Permission.`updateF`) as updateF, MAX(Permission.`deleteF`) as deleteF, MAX(Permission.`importF`) as importF, MAX(Permission.`exportF`) as exportF 
                    FROM resources Resource1
                    inner join resources Resource2 on Resource2.lft <= Resource1.lft AND Resource2.rht >= Resource1.rht AND Resource1.groupName="prometheus" AND Resource2.groupName="prometheus"
                    left join permissions Permission on Permission.resourceId = Resource2.id
                    inner join memberships Membership on Membership.roleId = Permission.roleId AND Membership.userId = getModelId()
                    GROUP BY Resource2.id
                    UNION ALL
                    SELECT Permission.id as id, Aclcontroller.relatedId as userId, Resource2.entity, MAX(Permission.readF) as readF, MAX(Permission.`searchF`) as searchF, MAX(Permission.`createF`) as createF, MAX(Permission.`updateF`) as updateF, MAX(Permission.`deleteF`) as deleteF, MAX(Permission.`importF`) as importF, MAX(Permission.`exportF`) as exportF 
                    FROM resources Resource1
                    inner join resources Resource2 on Resource2.lft <= Resource1.lft AND Resource2.rht >= Resource1.rht AND Resource1.groupName="Prometheus" AND Resource2.groupName="Prometheus"
                    left join permissions Permission on Permission.resourceId = Resource2.id
                    inner join acl_controllers Aclcontroller on Aclcontroller.id = Permission.controllerId AND Aclcontroller.relatedId = getModelId()
                    GROUP BY Resource2.id;',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
            'identifier' => true
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'entity' => array(
            'name' => 'entity',
            'type' => 'varchar',
            'null' => false,
        ),
        'readF' => array(
            'name' => 'readF',
            'type' => 'varchar',
            'null' => false,
        ),
        'searchF' => array(
            'name' => 'searchF',
            'type' => 'varchar',
            'null' => false,
        ),
        'createF' => array(
            'name' => 'createF',
            'type' => 'varchar',
            'null' => false,
        ),
        'updateF' => array(
            'name' => 'updateF',
            'type' => 'varchar',
            'null' => false,
        ),
        'deleteF' => array(
            'name' => 'deleteF',
            'type' => 'varchar',
            'null' => false,
        ),
        'importF' => array(
            'name' => 'importF',
            'type' => 'varchar',
            'null' => false,
        ),
        'exportF' => array(
            'name' => 'exportF',
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
