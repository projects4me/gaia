<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Usergrouppermission'] = array(
    'tableName' => 'user_group_permissions',
    'viewSql' => 'SELECT MIN("Permission".id) as id, "Membership"."relatedId" as "relatedId", "Membership"."relatedTo" as "groupName", "Permission"."resourceName" as entity, MAX("Permission"."allowed") as "allowed"
                    FROM permissions "Permission"
                    INNER JOIN memberships "Membership" ON "Membership"."roleId" = "Permission"."roleId" AND "Membership"."userId" = getcurrentuserid() AND "Membership"."relatedId" = getmodelid()
                    WHERE "Permission"."resourceName" IS NOT NULL
                    GROUP BY "Membership"."relatedId", "Membership"."relatedTo", "Permission"."resourceName"',
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
