<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userpermission'] = array(
    'tableName' => 'user_permissions',
    'viewSql' => 'SELECT MIN("Permission".id) as id, "Membership"."userId" as "userId", "Permission"."resourceName" as entity, MAX("Permission"."allowed") as "allowed"
                    FROM permissions "Permission"
                    INNER JOIN memberships "Membership" ON "Membership"."roleId" = "Permission"."roleId" AND "Membership"."userId" = getmodelid()
                    WHERE "Permission"."resourceName" IS NOT NULL
                    GROUP BY "Membership"."userId", "Permission"."resourceName"',
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
        'allowed' => array(
            'name' => 'allowed',
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
