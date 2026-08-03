<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\MVC\Models\Permission;

/**
 * RBAC/ACL policy layer for REST authorization.
 *
 * RestController wires HTTP context into this class. Permission owns loading
 * and storing effective permission rows. Acl only evaluates those rows — it
 * never keeps its own permission cache.
 *
 * Responsibilities:
 * - Ensure Permission has loaded the current user's effective grants
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
     * Class-level default until exposed through application configuration.
     * Selectable through the constructor or setPermissionResolutionMode().
     *
     * @var string
     */
    protected static $resolutionMode = self::RESOLUTION_PERMISSIVE;

    /**
     * Fields the user may see after field ACL is applied.
     *
     * @var array
     */
    private $allowedFields = [];

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

        self::$resolutionMode = $resolutionMode;
        return $this;
    }

    /**
     * Ensure Permission has loaded effective grants for the user.
     *
     * @param  string $userId
     * @return void
     */
    protected function loadPermissions($userId)
    {
        Permission::loadEffectivePermissions($userId);
    }

    /**
     * Load user action permissions and authorize a single action resource.
     *
     * @param  string $resourceName e.g. issue.create
     * @param  string $userId
     * @return $this
     * @throws \Gaia\Exception\Access
     */
    public function authorizeAction($resourceName, $userId)
    {
        $this->loadPermissions($userId);
        $this->checkAccess($resourceName);
        return $this;
    }

    /**
     * Return only relationship aliases the current user has access to.
     *
     * Related modules are checked as `{module}.{action}` (e.g. issue.get).
     *
     * @param  string $modelAlias
     * @param  array  $rels
     * @param  string $action
     * @return array
     */
    public function filterAuthorizedRelationships($modelAlias, array $rels, $action)
    {
        return array_filter($rels, function ($relName) use ($modelAlias, $action) {
            $relatedModelName = $this->getRelatedModelName($modelAlias, $relName);
            return $this->isResourceAllowed(
                $this->buildResourceName($relatedModelName, $action)
            );
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
        $this->checkAccess($this->buildResourceName($relatedModelName, $action));
    }

    /**
     * Authorize related modules actively referenced by query clauses.
     *
     * Unlike passive relationship inclusion, an unauthorized clause cannot be
     * safely omitted: doing so changes query semantics and can expose protected
     * values through result membership, ordering, or grouping. The whole
     * request is therefore denied before query construction.
     *
     * Base-model fields are covered by the request's primary authorizeAction.
     * Related aliases require the same action on the related module
     * (`{module}.{action}`). Field-level ACL is out of scope.
     *
     * Unknown aliases are left to normal query validation.
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

            if ($alias === $modelAlias) {
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
            $this->denyUnlessAllowed(
                $this->buildResourceName($relatedModelName, $action)
            );
        }
    }

    /**
     * Throw a generic deny when a resource is not allowed for active query
     * usage.
     *
     * @param  string $resourceName
     * @return void
     * @throws \Gaia\Exception\Access
     */
    protected function denyUnlessAllowed($resourceName)
    {
        if (!$this->isResourceAllowed($resourceName)) {
            throw new \Gaia\Exception\Access('Access Denied to requested query criteria');
        }
    }

    /**
     * Build an action resource name from a module/model name and action.
     *
     * @param  string $moduleOrModel
     * @param  string $action
     * @return string e.g. issue.create
     */
    protected function buildResourceName($moduleOrModel, $action)
    {
        return strtolower($moduleOrModel) . '.' . $action;
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
     * Authorize the action resource, or throw if denied.
     *
     * @param  string $resourceName e.g. issue.create
     * @return bool
     * @throws \Gaia\Exception\Access
     */
    public function checkAccess($resourceName)
    {
        if ($this->isResourceAllowed($resourceName)) {
            return true;
        }

        throw new \Gaia\Exception\Access("Access Denied to {$resourceName}");
    }

    /**
     * Whether the action resource is allowed under the current resolution mode.
     *
     * Missing grants follow resolution mode: permissive allows, restrictive denies.
     *
     * @param  string $resourceName
     * @return bool
     */
    /**
     * Whether the action resource is allowed under the current resolution mode.
     *
     * Missing grants follow resolution mode: permissive allows, restrictive denies.
     * Permissive: any allow wins. Restrictive: every row must allow.
     *
     * @param  string $resourceName
     * @return bool
     */
    protected function isResourceAllowed($resourceName)
    {
        if (!Permission::hasResource($resourceName)) {
            return self::$resolutionMode === self::RESOLUTION_PERMISSIVE;
        }

        foreach (Permission::getPermissionsForResource($resourceName) as $permission) {
            $allowed = $this->normalizeFlag($permission['allowed'] ?? null) === 1;

            if (self::$resolutionMode === self::RESOLUTION_PERMISSIVE && $allowed) {
                return true;
            }

            if (self::$resolutionMode === self::RESOLUTION_RESTRICTIVE && !$allowed) {
                return false;
            }
        }

        return self::$resolutionMode === self::RESOLUTION_RESTRICTIVE;
    }

    /**
     * Normalize a stored `allowed` flag to binary allow (1) or deny (0).
     *
     * @param  mixed $flagValue
     * @return int
     */
    protected function normalizeFlag($flagValue)
    {
        if ($flagValue === null || $flagValue === '') {
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

        ($this->isResourceAllowed($field)) && ($fields[$allowedField] = $allowedField);
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
            ($this->isResourceAllowed($field)) && ($fields[] = $allowedField);
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
