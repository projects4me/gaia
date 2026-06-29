<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userpermission'] = array(
    'tableName' => 'user_permissions',
    'viewSql' => 'SELECT "Permission".id as id, "Membership"."userId" as "userId", "Resource2".entity, MAX("Permission"."readF") as "readF", MAX("Permission"."createF") as "createF", MAX("Permission"."updateF") as "updateF", MAX("Permission"."deleteF") as "deleteF", MAX("Permission"."importF") as "importF", MAX("Permission"."exportF") as "exportF"
                    FROM resources "Resource1"
                    INNER JOIN resources "Resource2" ON "Resource2".lft <= "Resource1".lft AND "Resource2".rht >= "Resource1".rht AND "Resource1"."groupName" = \'prometheus\' AND "Resource2"."groupName" = \'prometheus\'
                    LEFT JOIN permissions "Permission" ON "Permission"."resourceId" = "Resource2".id
                    INNER JOIN memberships "Membership" ON "Membership"."roleId" = "Permission"."roleId" AND "Membership"."userId" = getmodelid()
                    GROUP BY "Resource2".id, "Permission".id, "Membership"."userId", "Resource2".entity
                    UNION ALL
                    SELECT "Permission".id as id, "Aclcontroller"."relatedId" as "userId", "Resource2".entity, MAX("Permission"."readF") as "readF", MAX("Permission"."createF") as "createF", MAX("Permission"."updateF") as "updateF", MAX("Permission"."deleteF") as "deleteF", MAX("Permission"."importF") as "importF", MAX("Permission"."exportF") as "exportF"
                    FROM resources "Resource1"
                    INNER JOIN resources "Resource2" ON "Resource2".lft <= "Resource1".lft AND "Resource2".rht >= "Resource1".rht AND "Resource1"."groupName" = \'Prometheus\' AND "Resource2"."groupName" = \'Prometheus\'
                    LEFT JOIN permissions "Permission" ON "Permission"."resourceId" = "Resource2".id
                    INNER JOIN acl_controllers "Aclcontroller" ON "Aclcontroller".id = "Permission"."controllerId" AND "Aclcontroller"."relatedId" = getmodelid()
                    GROUP BY "Resource2".id, "Permission".id, "Aclcontroller"."relatedId", "Resource2".entity',
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
