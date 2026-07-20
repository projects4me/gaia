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
     * Fields the user may see in API responses after field ACL is applied.
     *
     * @var array $allowedFields
     */
    private $allowedFields = [];

    public $projectId = null;

    /**
     * This function is used to check whether the user has access to given resource or not.
     *
     * @param string $resource      Name of the resource.
     * @param string $resourceAlias Alias of the resource.
     */
    public function checkModelAccess($resource, $resourceAlias, $action)
    {
        if (!$this->checkAccess($resource, $resourceAlias, $action)) {
            throw new \Gaia\Exception\Access("Access Denied to $resource");
        }
    }

    /**
     * This function is used to check whether the user has access to given list of
     * relationships or not.
     *
     * @param string $resource Name of the resource.
     * @param array  $rels     Array of model relationships.
     */
    public function checkRelsAccess($resource, $rels, $action)
    {
        $di = \Phalcon\Di::getDefault();

        foreach (array_keys($rels) as $relName) {
            $relatedModelName = $di->get('metaManager')->getRelatedModelName($resource, $relName);
            $this->checkAccess($relatedModelName, $relName, $action, true);
        }
    }

    /**
     * Check whether the given resource is allowed for the requested action.
     * Permission flags are binary: 0 = deny, 1 = allow. Legacy non-zero values
     * (and null, historically treated as full access) are normalized to allow.
     * Resources with no stored permission entry remain allowed (legacy behavior).
     *
     * @param string $resource      Name of the resource.
     * @param string $resourceAlias Alias of the resource.
     * @param string $action        Permission flag name (e.g. readF).
     * @param bool   $isRel         Whether this check is for a relationship.
     * @return bool
     */
    public function checkAccess($resource, $resourceAlias = null, $action= 'readF', $isRel = false)
    {
        if (!isset($this->permissions[$resource])) {
            return true;
        }

        $isAllowed = false;
        $permissions = $this->permissions[$resource];

        foreach ($permissions as $permission) {
            $flagValue = array_key_exists($action, $permission) ? $permission[$action] : null;
            $normalizedFlag = $this->normalizeFlag($flagValue);

            if ($normalizedFlag === 1) {
                $isAllowed = true;
            }

            $this->resourcesPermissions[$resource][] = [
                'accessLevel' => $normalizedFlag,
                'projectId' => $permission['projectId']
            ];
        }

        if (!$isAllowed && !$isRel) {
            // Field resources (Model.field) must not 403 — RestController nulls denied fields.
            if (str_contains($resource, '.')) {
                return false;
            }
            throw new \Gaia\Exception\Access("Access Denied to $resource");
        }

        return true;
    }

    /**
     * Normalize a stored permission flag to binary allow (1) or deny (0).
     * Legacy values above 1 (e.g. 2, 8, 9) and null are treated as allow until data is migrated.
     *
     * @param mixed $flagValue
     * @return int
     */
    protected function normalizeFlag($flagValue)
    {
        if ($flagValue === null) {
            return 1;
        }

        if ($flagValue === '') {
            return 0;
        }

        return ((int) $flagValue > 0) ? 1 : 0;
    }

    /**
     * This function is used to fetch user permissions based on given action.
     *
     * @param string $userId    The identifier of user.
     * @param string $action    Name of action for which permission is to be fetched.
     * @param string $modelName The name of model.
     * @param array  $params    The user requested parameters.
     * @param string $projectId The identifier of the project.
     */
    public function fetchUserPermissions($userId, $action, $modelName, $params, $projectId)
    {
        $results = [];
        $permissions = [];
        $di = \Phalcon\Di::getDefault();

        $projectId = $projectId ?? $this->getProjectId($modelName, $params);
        if ($projectId) {
            $this->projectId = $projectId;
            $membershipQueryBuilder = $di->get('modelsManager')->createBuilder();

            $membershipQueryBuilder->columns(["Membership.roleId"]);
            $membershipQueryBuilder->from(['Membership' => 'Gaia\\MVC\\Models\\Membership']);
            $membershipQueryBuilder->where(
                'Membership.relatedId=:projectId:',
                ["projectId" => $projectId]
            );
            $membershipQueryBuilder->andWhere(
                'Membership.userId=:userId:',
                ["userId" => $userId]
            );

            $membership = $membershipQueryBuilder->getQuery()->getSingleResult();
        }
        
        $membershipRoleIdCondition = '';
        
        (isset($membership->roleId))
            && ($membershipRoleIdCondition = "AND Membership.roleId='{$membership->roleId}'");

        //Fetch Permissions of User by Role
        $permissionsByRole = (new self())->buildPermissionsQuery(null, $action);
        $permissionsByRole->innerJoin("Gaia\\MVC\\Models\\Membership", "Membership.roleId=Permission.roleId $membershipRoleIdCondition AND Membership.userId='$userId'", "Membership");

        $results[] = $permissionsByRole->getQuery()->execute();

        foreach ($results as $permission) {
            foreach ($permission as $value) {
                if (!isset($permissions[$value->entity])) {
                    $permissions[$value->entity] = [];
                }
                $permissions[$value->entity][] = [
                    $action => $value->$action,
                    'projectId' => $value->projectId
                ];
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
        $queryBuilder->columns(["Permission.$action", 'Resource1.entity', 'Membership.relatedId as projectId']);
        $queryBuilder->from(['Resource1' => 'Gaia\\MVC\\Models\\Resource']);

        //joins
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

    /**
     * Apply ACL on model/related model fields. Only fields the user can access
     * are kept for the API response.
     *
     * @method applyACLOnFields
     * @param  array  $values
     * @param  string $modelAlias
     * @param  array  $params
     * @return void
     */
    public function applyACLOnFields($values, $modelAlias, $params)
    {
        $fields = [];

        foreach ($values as $fieldName => $value) {
            if (!is_array($value)) {
                $this->applyACLByScalarField($fieldName, $modelAlias, $params, $fields);
            } else {
                $this->applyACLByObjectField($fieldName, $modelAlias, $value, $fields);
            }
        }

        $this->allowedFields = $fields;
    }

    /**
     * This function is used to apply ACL on a scalar field.
     * 
     * @param  string $fieldName
     * @param  string $modelAlias
     * @param  array  $params
     * @param  array  $fields
     * @return void
     */
    protected function applyACLByScalarField($fieldName, $modelAlias, $params, &$fields)
    {
        $di = \Phalcon\Di::getDefault();
        $field = "{$modelAlias}.{$fieldName}";
        $allowedField = $field;
        $aliasByFields = $this->getAliasByFields($params);

        if (in_array($fieldName, array_keys($aliasByFields))) {
            list($moduleName, $moduleField) = explode('.', $aliasByFields[$fieldName]);
            $isModel = $modelAlias === $moduleName;

            if (!$isModel) {
                $relName = $moduleName;
                $moduleName = $di->get('metaManager')->getRelatedModelName($modelAlias, $relName);
            }
            $field = "{$moduleName}.{$moduleField}";
            $allowedField = $fieldName;
        }

        ($this->checkAccess($field)) && ($fields[$allowedField] = $allowedField);
    }

    /**
     * This function is used to apply ACL on an object field.
     * 
     * @param  string $fieldName
     * @param  string $modelAlias
     * @param  array  $values
     * @param  array  $fields
     * @return void
     */
    protected function applyACLByObjectField($fieldName, $modelAlias, $values, &$fields)
    {
        $di = \Phalcon\Di::getDefault();
        $isModel = $modelAlias === $fieldName;

        if (!$isModel) {
            $relName = $fieldName;
            $relatedModelName = $di->get('metaManager')->getRelatedModelName($modelAlias, $relName);
        }

        foreach (array_keys($values) as $nestedField) {
            $allowedField = "{$fieldName}.{$nestedField}";
            $field = ($isModel) ? ($allowedField) : "{$relatedModelName}.{$nestedField}";
            ($this->checkAccess($field)) && ($fields[] = $allowedField);
        }
    }

    /**
     * This function is used to get the alias by fields.
     * 
     * @param  array $params
     * @return array
     */
    protected function getAliasByFields($params)
    {
        $aliasByFields = [];
        if (empty($params['fields'])) {
            return $aliasByFields;
        }

        foreach ($params['fields'] as $requestedField) {
            if (str_contains(strtoupper($requestedField), "AS")) {
                list($field, , $alias) = explode(" ", $requestedField);
                $aliasByFields[$alias] = $field;
            }
        }

        return $aliasByFields;
    }

    /**
     * @method getAllowedFields
     * @return array
     */
    public function getAllowedFields()
    {
        return $this->allowedFields;
    }

    /**
     * This function extract project ID from the given parameters (if available) and return it.
     *
     * @param  string $modelName The name of model.
     * @param  array  $params    The user requested parameters.
     * @return string|null The project ID if found, otherwise null.
     */
    protected function getProjectId($modelName, $params)
    {
        $modelQuery = $this->instantiateQuery($modelName, $params);
        $modelQuery->prepareClauses($params, $modelQuery);
        $whereClauses = $modelQuery->getClause()->getWhereClause('original', $modelName);
        $possibleKeys = ['projectId', 'Project.id'];
        $edgeCaseKey = "relatedTo : project";
        $edgeCasePassed = false;
        $projectId = null;

        foreach ($whereClauses as $clause) {
            foreach ($possibleKeys as $key) {
                if (strpos($clause, $key) !== false) {
                    list(, , $projectId) = str_replace(')', '', explode(' ', $clause));
                    return $projectId;
                }
            }

            // Check for the edge case with 'relatedTo'
            if (strpos($clause, $edgeCaseKey) !== false) {
                $edgeCasePassed = true;
                break;
            }
        }

        // If edge case is passed then findout projectId from relatedId
        if ($edgeCasePassed) {
            foreach ($whereClauses as $clause) {
                if (strpos($clause, 'relatedId') !== false) {
                    list(, , $projectId) = str_replace(')', '', explode(' ', $clause));
                    return $projectId;
                }
            }
        }

        return $projectId;
    }
}
