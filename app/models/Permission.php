<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\Util;

/**
 * Permission Model
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Model
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Permission extends Model
{
    /**
     * This contains all of the permissions that the user have.
     *
     * @var array $permissions
     */
    protected $permissions = [];

    /**
     * This contains all of the permissions of each resource on which user has access.
     *
     * @var array $resourcesPermissions
     */
    protected $resourcesPermissions = [];

    /**
     * Prefix of the resource.
     *
     * @var string $resourcePrefix
     */
    protected $resourcePrefix;

    /**
     * This function is used to check whether the user has access to given resource or not.
     *
     * @param string $resource      Name of the resource.
     * @param array  $actionMap     Array of acl action map.
     * @param string $resourceAlias Alias of the resource.
     */
    public function checkModelAccess($resource, $actionMap, $resourceAlias)
    {
        if (!$this->checkAccess($resource, $actionMap, $resourceAlias)) {
            throw new \Gaia\Exception\Access("Access Denied to $resource");
        }
    }

    /**
     * This function is used to check whether the user has access to given list of
     * relationships or not.
     *
     * @param string $resource  Name of the resource.
     * @param array  $rels      Array of model relationships.
     * @param array  $actionMap Array of acl action map.
     */
    public function checkRelsAccess($resource, $rels, $actionMap)
    {
        foreach (array_keys($rels) as $relName) {
            $this->checkAccess("$resource.$relName", $actionMap, $relName);
        }
    }

    /**
     * This function is used to check whether the given resource has access. This is the function contains the main
     * logic for checking the access of the given resource. If there is no access against the resource, this will try
     * to retreive parent resource access. After getting the resource permission, that resource is added into the
     * resourcesPermissions, which is key value paired array containing the resource name as key and access level as
     * its value.
     *
     * @param string $resource      Name of the resource.
     * @param array  $actionMap     Array of acl action map.
     * @param string $resourceAlias Alias of the resource.
     */
    public function checkAccess($resource, $actionMap, $resourceAlias)
    {
        $parentResource = $this->getParentResource($resource, $actionMap['action'], $resourceAlias);
        $alias = ($resourceAlias ?? $resource);
        $accessGranted = false;

        $resource = ($this->resourcePrefix) ? ("$this->resourcePrefix.$resource") : $resource;
        if (isset($this->permissions[$resource]) && max($this->permissions[$resource])) {
            $this->resourcesPermissions[$alias] = max($this->permissions[$resource]);
            $accessGranted = true;
        } elseif (isset($this->permissions[$parentResource->entity])
            && max($this->permissions[$parentResource->entity])
        ) {
            $this->resourcesPermissions[$alias] = max($this->permissions[$parentResource->entity]);
            $accessGranted = true;
        } else {
            $accessGranted = false;
        }

        return $accessGranted;
    }

    /**
     * This function is used to fetch user permissions based on given action.
     *
     * @param string $userId The identifier of user.
     * @param string $action Name of action for which permission is to be fetched.
     */
    public function fetchUserPermissions($userId, $action)
    {
        $results = [];
        $permissions = [];

        //Fetch Permissions of User by Role
        $permissionsByRole = (new self())->buildPermissionsQuery(null, $action);
        $permissionsByRole->innerJoin("Gaia\\MVC\\Models\\Membership", "Membership.roleId=Permission.roleId AND Membership.userId='$userId'", "Membership");
        $permissionsByRole->groupBy(['Membership.roleId', 'Resource2.id']);

        $results[] = $permissionsByRole->getQuery()->execute();

        //Fetch Permissions of User
        $permissionsByUser = (new self())->buildPermissionsQuery(null, $action);
        $permissionsByUser->innerJoin("Gaia\\MVC\\Models\\Aclcontroller", "Aclcontroller.id=Permission.controllerId AND Aclcontroller.relatedId='$userId'", "Aclcontroller");
        $results[] = $permissionsByUser->getQuery()->execute();

        foreach ($results as $permission) {
            foreach ($permission as $value) {
                $permissions[$value->entity][] = $value->$action;
            }
        }

        $this->permissions = $permissions;
    }

    /**
     * This function is used to setup the permissions query using the phalcon query builder.
     *
     * @param  string $resource
     * @param  string $action
     * @return \Phalcon\Mvc\Model\Query\Builder
     */
    private function buildPermissionsQuery($resource = null, $action = null)
    {
        $di = \Phalcon\Di::getDefault();

        $queryBuilder = $di->get('modelsManager')->createBuilder();
        $queryBuilder->columns(["Permission.$action", 'Resource1.entity']);
        $queryBuilder->from(['Resource1' => 'Gaia\\MVC\\Models\\Resource']);

        //joins
        $queryBuilder->leftJoin(
            "Gaia\\MVC\\Models\\Resource",
            "Resource2.lft <= Resource1.lft AND Resource2.rht >= Resource1.rht",
            "Resource2"
        );
        $queryBuilder->leftJoin("Gaia\\MVC\\Models\\Permission", "Permission.resourceId=Resource1.id", "Permission");

        //clauses
        ($resource) && ($queryBuilder->where('Resource1.entity=:resource:', ["resource" => $resource]));
        return $queryBuilder;
    }

    /**
     * This function returns access of a given resource.
     *
     * @param  string $resource
     * @return string
     */
    public function getAccess($resource)
    {
        return $this->resourcesPermissions[$resource] ?? null;
    }

    /**
     * This function is used to set the prefix of the resource, that will be useful
     * if we want some custom nomenclature for the resources.
     *
     * @param string $prefix
     */
    public function setResourcePrefix($prefix)
    {
        $this->resourcePrefix = $prefix;
    }

    /**
     * This function is used to fetch parent of the given resource.
     *
     * @param  string $childResource
     * @return \Phalcon\Mvc\Model\Row
     */
    protected function getParentResource($childResource)
    {
        $di = \Phalcon\Di::getDefault();

        $queryBuilder = $di->get('modelsManager')->createBuilder();
        $queryBuilder->columns(["Resource2.entity"]);
        $queryBuilder->from(['Resource1' => 'Gaia\\MVC\\Models\\Resource']);
        $queryBuilder->leftJoin(
            "Gaia\\MVC\\Models\\Resource",
            "Resource2.id = Resource1.parentId",
            "Resource2"
        );

        $queryBuilder->where('Resource1.entity=:resource:', ["resource" => $childResource]);
        return $queryBuilder->getQuery()->getSingleResult();
    }
}
