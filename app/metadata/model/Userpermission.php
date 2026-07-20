<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userpermission'] = array(
    'tableName' => 'user_permissions',
    'viewSql' => 'SELECT MIN("Permission".id) as id, "Membership"."userId" as "userId", "Resource".entity, MAX("Permission"."readF") as "readF", MAX("Permission"."createF") as "createF", MAX("Permission"."updateF") as "updateF", MAX("Permission"."deleteF") as "deleteF", MAX("Permission"."importF") as "importF", MAX("Permission"."exportF") as "exportF"
                    FROM permissions "Permission"
                    INNER JOIN resources "Resource" ON "Resource".id = "Permission"."resourceId" AND "Resource"."groupName" = \'gaia\' AND "Resource".entity NOT LIKE \'%.%\' AND "Resource".entity <> \'App\'
                    INNER JOIN memberships "Membership" ON "Membership"."roleId" = "Permission"."roleId" AND "Membership"."userId" = getmodelid()
                    GROUP BY "Membership"."userId", "Resource".entity',
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
