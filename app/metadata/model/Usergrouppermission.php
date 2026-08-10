<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usergrouppermission'] = array(
    'tableName' => 'user_group_permissions',
    'viewSql' => 'SELECT MIN("Permission".id) as id, "Membership"."projectId" as "relatedId", \'project\' as "groupName", "Permission"."resourceName" as entity, MAX("Permission"."allowed") as "allowed"
                    FROM permissions "Permission"
                    INNER JOIN user_roles "UserRole" ON "UserRole"."roleId" = "Permission"."roleId" AND "UserRole".deleted = \'0\'
                    INNER JOIN memberships "Membership" ON "Membership"."userId" = "UserRole"."userId" AND "Membership"."userId" = getcurrentuserid() AND "Membership"."projectId" = getmodelid()
                    WHERE "Permission"."resourceName" IS NOT NULL
                    GROUP BY "Membership"."projectId", "Permission"."resourceName"',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
            'identifier' => true
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
