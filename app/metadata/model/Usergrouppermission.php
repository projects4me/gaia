<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usergrouppermission'] = array(
    'tableName' => 'user_group_permissions',
    'viewSql' => 'SELECT Permission.id as id, Membership.relatedId as relatedId, Membership.relatedTo as groupName, Resource2.entity as entity, MAX(Permission.readF) as readF, MAX(Permission.`searchF`) as searchF, MAX(Permission.`createF`) as createF, MAX(Permission.`updateF`) as updateF, MAX(Permission.`deleteF`) as deleteF, MAX(Permission.`importF`) as importF, MAX(Permission.`exportF`) as exportF
                    from resources as Resource1
                    left join resources Resource2 on Resource2.lft <= Resource1.lft AND Resource2.rht >= Resource1.rht AND Resource1.groupName="prometheus" AND Resource2.groupName="prometheus"
                    left join permissions Permission on Permission.resourceId = Resource2.id
                    inner join memberships Membership on Membership.roleId = Permission.roleId AND Membership.userId = getCurrentUserId() AND Membership.relatedId =getModelId()
                    GROUP BY Resource2.id;',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'type' => 'varchar',
            'null' => false,
        ),
        'groupName' => array(
            'name' => 'groupName',
            'type' => 'varchar',
            'null' => false,
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
    'functions' => array(),
    'customIdentifiers' => []
);

return $models;
