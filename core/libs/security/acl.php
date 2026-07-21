<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Core\MVC\Models\Query;
use Gaia\MVC\Models\Permission;

/**
 * Request-scoped RBAC/ACL policy layer for REST authorization.
 *
 * RestController wires HTTP context into this class. Permission remains the
 * persistence/query model that loads effective permission rows from the DB.
 *
 * Responsibilities:
 * - Load and hold the current user's effective permissions for a request
 * - Authorize direct model access (denial throws)
 * - Filter relationship aliases the user may not read (denial omits)
 * - Authorize related-resource routes (denial throws)
 * - Apply field-level ACL for API responses
 *
 * @author  Rana Nouman <ranamnouman@gmail.com>
 * @package Core\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Acl
{
    /**
     * A permission is allowed when any applicable role grants it.
     */
    public const RESOLUTION_PERMISSIVE = 'permissive';

    /**
     * A permission is denied when any applicable role denies it.
     */
    public const RESOLUTION_RESTRICTIVE = 'restrictive';

    /**
     * @var \Phalcon\Di\FactoryDefault
     */
    protected $di;

    /**
     * Strategy used to combine permission flags from multiple applicable roles.
     *
     * This remains a class-level default until it is exposed through application
     * configuration. It can already be selected through the constructor or
     * setPermissionResolutionMode().
     *
     * @var string
     */
    protected $resolutionMode = self::RESOLUTION_PERMISSIVE;

    /**
     * Effective permissions for the current request, keyed by resource entity.
     *
     * @var array
     */
    protected $permissions = [];

    /**
     * Access decisions recorded while checking resources.
     *
     * @var array
     */
    protected $resourcesPermissions = [];

    /**
     * Fields the user may see after field ACL is applied.
     *
     * @var array
     */
    private $allowedFields = [];

    /**
     * Resolved project scope for the current request, if any.
     *
     * @var string|null
     */
    public $projectId = null;

    /**
     * The constructor of the Acl class.
     * 
     * @param \Phalcon\Di\FactoryDefault $di
     * @param string $resolutionMode
     */
    public function __construct(
        \Phalcon\Di\FactoryDefault $di,
        $resolutionMode = self::RESOLUTION_PERMISSIVE
    ) {
        $this->di = $di;
        $this->setPermissionResolutionMode($resolutionMode);
    }

    /**
     * Select how flags from multiple roles are combined.
     *
     * @param  string $resolutionMode
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setPermissionResolutionMode($resolutionMode)
    {
        $supportedModes = [
            self::RESOLUTION_PERMISSIVE,
            self::RESOLUTION_RESTRICTIVE,
        ];

        if (!in_array($resolutionMode, $supportedModes, true)) {
            throw new \InvalidArgumentException(
                "Unsupported ACL permission resolution mode: {$resolutionMode}"
            );
        }

        $this->resolutionMode = $resolutionMode;
        return $this;
    }

    /**
     * Load the current user's permissions and authorize direct model access.
     *
     * @param  string      $resource   Camelized resource/model name (e.g. "Project").
     * @param  string      $modelAlias Model alias used to resolve permission context.
     * @param  string      $action     Permission flag being checked (e.g. readF).
     * @param  string      $userId     Identifier of the current user.
     * @param  array       $params     Request params used to resolve project context.
     * @param  string|null $projectId  Explicit project scope, if already known.
     * @return $this
     * @throws \Gaia\Exception\Access
     */
    public function authorizeModel($resource, $modelAlias, $action, $userId, $params, $projectId = null)
    {
        $projectId = $projectId ?? $this->resolveProjectId($modelAlias, $params);
        if ($projectId) {
            $this->projectId = $projectId;
        }

        $permissionModel = new Permission();
        $this->permissions = $permissionModel->findEffectivePermissions($userId, $action, $projectId);
        $this->checkModelAccess($resource, $action);
        return $this;
    }

    /**
     * Return only relationship aliases the current user has access to .
     * 
     * @param  string $modelAlias
     * @param  array  $rels
     * @param  string $action
     * @return array
     */
    public function filterAuthorizedRelationships($modelAlias, array $rels, $action)
    {
        return array_filter($rels, function ($relName) use ($modelAlias, $action) {
            return $this->checkAccess($this->getRelatedModelName($modelAlias, $relName), $action, true);
        });
    }

    /**
     * Authorize `GET /resource/{id}/{relation}` as direct access to the related model.
     *
     * @param  string $modelAlias
     * @param  string $relation
     * @param  string $action
     * @throws \Gaia\Exception\Access
     */
    public function authorizeRelated($modelAlias, $relation, $action)
    {
        $relatedModelName = $this->getRelatedModelName($modelAlias, $relation);
        $this->checkModelAccess($relatedModelName, $action);
    }

    /**
     * Authorize every model/field actively referenced by query clauses.
     *
     * Unlike passive relationship inclusion, an unauthorized clause cannot be
     * safely omitted: doing so changes query semantics and can expose protected
     * values through result membership, ordering, or grouping. The whole
     * request is therefore denied before query construction.
     *
     * Unknown aliases are left to normal query validation. This method handles
     * only the base model and relationship aliases defined in model metadata.
     *
     * @param  string $modelAlias
     * @param  string $action
     * @param  array  $clauses Supported keys: query, sort, groupBy, having.
     * @return void
     * @throws \Gaia\Exception\Access
     */
    public function authorizeClauseUsage($modelAlias, $action, array $clauses)
    {
        foreach ($this->getClauseFieldReferences($clauses) as $reference) {
            $alias = $reference['alias'];
            $field = $reference['field'];

            if ($alias === $modelAlias) {
                $this->denyUnlessAllowed("{$modelAlias}.{$field}", $action);
                continue;
            }

            $relMeta = $this->di->get('metaManager')->getRelationshipMeta(
                $modelAlias,
                $alias,
                false
            );

            if (empty($relMeta)) {
                continue;
            }

            $relatedModelName = $this->getRelatedModelName($modelAlias, $alias);
            $this->denyUnlessAllowed($relatedModelName, $action, true);
            $this->denyUnlessAllowed("{$relatedModelName}.{$field}", $action);
        }
    }

    /**
     * Throw a generic deny when a resource is not allowed for active query
     * usage.
     *
     * @param  string $resource
     * @param  string $action
     * @param  bool   $isRel
     * @return void
     * @throws \Gaia\Exception\Access
     */
    protected function denyUnlessAllowed($resource, $action, $isRel = false)
    {
        if (!$this->checkAccess($resource, $action, $isRel)) {
            throw new \Gaia\Exception\Access('Access Denied to requested query criteria');
        }
    }

    /**
     * Extract field references from query clauses.
     *
     * @param  array $clauses
     * @return array
     */
    protected function getClauseFieldReferences(array $clauses)
    {
        $references = [];
        $query = isset($clauses['query']) ? $clauses['query'] : '';

        if (is_string($query) && $query !== '') {
            preg_match_all(
                '/\(\s*([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)\s+/',
                $query,
                $matches,
                PREG_SET_ORDER
            );
            $this->appendFieldReferences($references, $matches);
        }

        foreach (['sort', 'groupBy', 'having'] as $clauseName) {
            if (!isset($clauses[$clauseName]) || $clauses[$clauseName] === '') {
                continue;
            }

            $clause = is_array($clauses[$clauseName])
                ? implode(',', $clauses[$clauseName])
                : (string) $clauses[$clauseName];

            preg_match_all(
                '/(?<![A-Za-z0-9_])([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)/',
                $clause,
                $matches,
                PREG_SET_ORDER
            );
            $this->appendFieldReferences($references, $matches);
        }

        return array_values($references);
    }

    /**
     * Add unique alias/field references from matches.
     *
     * @param  array $references
     * @param  array $matches
     * @return void
     */
    protected function appendFieldReferences(&$references, array $matches)
    {
        foreach ($matches as $match) {
            $key = $match[1] . '.' . $match[2];
            $references[$key] = [
                'alias' => $match[1],
                'field' => $match[2],
            ];
        }
    }

    /**
     * Check if the user has access to the given resource. If not, throw an exception.
     *
     * @param  string $resource
     * @param  string $action
     * @throws \Gaia\Exception\Access
     */
    public function checkModelAccess($resource, $action)
    {
        if (!$this->checkAccess($resource, $action)) {
            throw new \Gaia\Exception\Access("Access Denied to $resource");
        }
    }

    /**
     * Evaluate whether the resource is allowed for the action. If not, return false.
     *
     * Permission flags are binary: 0 = deny, 1 = allow.
     *
     * @param  string $resource
     * @param  string $action
     * @param  bool   $isRel
     * @return bool
     * @throws \Gaia\Exception\Access
     */
    public function checkAccess($resource, $action = 'readF', $isRel = false)
    {
        if (!isset($this->permissions[$resource])) {
            return true;
        }

        $hasAllow = false;
        $hasDeny = false;
        $permissions = $this->permissions[$resource];

        foreach ($permissions as $permission) {
            $flagValue = array_key_exists($action, $permission) ? $permission[$action] : null;
            $normalizedFlag = $this->normalizeFlag($flagValue);

            if ($normalizedFlag === 1) {
                $hasAllow = true;
            } else {
                $hasDeny = true;
            }

            $this->resourcesPermissions[$resource][] = [
                'accessLevel' => $normalizedFlag,
                'projectId' => $permission['projectId'],
                'roleId' => isset($permission['roleId']) ? $permission['roleId'] : null
            ];
        }

        $isAllowed = ($this->resolutionMode === self::RESOLUTION_RESTRICTIVE)
            ? ($hasAllow && !$hasDeny)
            : $hasAllow;

        if ($isAllowed) {
            return true;
        }

        if ($isRel || str_contains($resource, '.')) {
            return false;
        }

        throw new \Gaia\Exception\Access("Access Denied to $resource");
    }

    /**
     * Normalize a stored permission flag to binary allow (1) or deny (0).
     *
     * @param  mixed $flagValue
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
     * Apply ACL on model/related model fields. Only fields the user can access
     * are kept for the API response.
     *
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
     * Apply ACL on a scalar field.
     * 
     * @param  string $fieldName
     * @param  string $modelAlias
     * @param  array  $params
     * @param  array  $fields
     * @return void
     */
    protected function applyACLByScalarField($fieldName, $modelAlias, $params, &$fields)
    {
        $field = "{$modelAlias}.{$fieldName}";
        $allowedField = $field;
        $aliasByFields = $this->getAliasByFields($params);

        if (in_array($fieldName, array_keys($aliasByFields))) {
            list($moduleName, $moduleField) = explode('.', $aliasByFields[$fieldName]);
            $isModel = $modelAlias === $moduleName;

            if (!$isModel) {
                $relName = $moduleName;
                $moduleName = $this->getRelatedModelName($modelAlias, $relName);
            }
            $field = "{$moduleName}.{$moduleField}";
            $allowedField = $fieldName;
        }

        ($this->checkAccess($field)) && ($fields[$allowedField] = $allowedField);
    }

    /**
     * Apply ACL on an object field.
     * 
     * @param  string $fieldName
     * @param  string $modelAlias
     * @param  array  $values
     * @param  array  $fields
     * @return void
     */
    protected function applyACLByObjectField($fieldName, $modelAlias, $values, &$fields)
    {
        $isModel = $modelAlias === $fieldName;
        $relatedModelName = null;

        if (!$isModel) {
            $relName = $fieldName;
            $relatedModelName = $this->getRelatedModelName($modelAlias, $relName);
        }

        foreach (array_keys($values) as $nestedField) {
            $allowedField = "{$fieldName}.{$nestedField}";
            $field = ($isModel) ? ($allowedField) : "{$relatedModelName}.{$nestedField}";
            ($this->checkAccess($field)) && ($fields[] = $allowedField);
        }
    }

    /**
     * Get the alias by fields.
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
                list($field,, $alias) = explode(" ", $requestedField);
                $aliasByFields[$alias] = $field;
            }
        }

        return $aliasByFields;
    }

    /**
     * Get the allowed fields.
     * 
     * @return array
     */
    public function getAllowedFields()
    {
        return $this->allowedFields;
    }

    /**
     * Get the access for the given resource.
     * 
     * @param  string $resource
     * @return array|null
     */
    public function getAccess($resource)
    {
        return $this->resourcesPermissions[$resource] ?? null;
    }

    /**
     * Resolve the project ID from request query parameters.
     *
     * @param  string $modelName
     * @param  array  $params
     * @return string|null
     */
    protected function resolveProjectId($modelName, $params)
    {
        $id = isset($params['id']) ? $params['id'] : null;
        $clauseParams = [
            'where' => isset($params['where']) ? $params['where'] : '',
            'sort' => isset($params['sort']) ? $params['sort'] : '',
            'order' => isset($params['order']) ? $params['order'] : 'DESC',
            'groupBy' => isset($params['groupBy']) ? $params['groupBy'] : [],
            'having' => isset($params['having']) ? $params['having'] : '',
        ];
        $modelQuery = new Query($this->di, $modelName, $id);
        $modelQuery->prepareClauses($clauseParams, $modelQuery);
        $whereClauses = $modelQuery->getClause()->getWhereClause('original', $modelName);
        $possibleKeys = ['projectId', 'Project.id'];
        $edgeCaseKey = "relatedTo : project";
        $edgeCasePassed = false;
        $projectId = null;

        foreach ($whereClauses as $clause) {
            foreach ($possibleKeys as $key) {
                if (strpos($clause, $key) !== false) {
                    list(,, $projectId) = str_replace(')', '', explode(' ', $clause));
                    return $projectId;
                }
            }

            if (strpos($clause, $edgeCaseKey) !== false) {
                $edgeCasePassed = true;
                break;
            }
        }

        if ($edgeCasePassed) {
            foreach ($whereClauses as $clause) {
                if (strpos($clause, 'relatedId') !== false) {
                    list(,, $projectId) = str_replace(')', '', explode(' ', $clause));
                    return $projectId;
                }
            }
        }

        return $projectId;
    }

    /**
     * Get the related model name.
     *
     * @param  string $modelAlias
     * @param  string $relName
     * @return string
     */
    protected function getRelatedModelName($modelAlias, $relName)
    {
        return $this->di->get('metaManager')->getRelatedModelName($modelAlias, $relName);
    }
}
