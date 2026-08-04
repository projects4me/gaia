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
 * - Authorize direct model access including authorization groups (denial throws)
 * - Filter relationship aliases the user may not read (denial omits)
 * - Authorize related-resource routes (denial throws)
 * - Authorize and filter field-level ACL (`{module}.{field}.{action}`)
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
     * Load user action permissions and authorize a model action, including
     * every authorization group declared on that model.
     *
     * @param  string $resourceName e.g. issue.create
     * @param  string $userId
     * @param  string|null $modelAlias Model name for group checks (e.g. Issue)
     * @return $this
     * @throws \Gaia\Exception\Access
     */
    public function authorizeAction($resourceName, $userId, $modelAlias = null)
    {
        $this->loadPermissions($userId);

        if ($modelAlias !== null && $modelAlias !== '') {
            $action = $this->extractActionFromResourceName($resourceName);
            $this->checkModelAccess($modelAlias, $action);
            return $this;
        }

        $this->checkAccess($resourceName);
        return $this;
    }

    /**
     * Return only relationship aliases the current user has access to.
     *
     * Related modules require `{module}.{action}` plus access to every
     * authorization group declared on that related module.
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
            return $this->isModelActionAllowed($relatedModelName, $action);
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
     * Authorize related modules and fields actively referenced by query clauses.
     *
     * Unlike passive relationship inclusion, an unauthorized clause cannot be
     * safely omitted: doing so changes query semantics and can expose protected
     * values through result membership, ordering, or grouping. The whole
     * request is therefore denied before query construction.
     *
     * Related aliases require the same action on the related module
     * (`{module}.{action}`) plus that module's authorization groups.
     * Every referenced field (base or related) also requires `{module}.{field}.get`
     * (via isFieldActionAllowed, which includes the module prerequisite).
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
        foreach ($this->getClauseFieldReferences($clauses, $modelAlias) as $reference) {
            $alias = $reference['alias'];
            $field = $reference['field'];
            $targetModel = $modelAlias;

            if ($alias !== $modelAlias) {
                $relMeta = $this->di->get('metaManager')->getRelationshipMeta(
                    $modelAlias,
                    $alias,
                    false
                );

                if (empty($relMeta)) {
                    continue;
                }

                $targetModel = $this->getRelatedModelName($modelAlias, $alias);
                if (!$this->isModelActionAllowed($targetModel, $action)) {
                    $this->denyAccess(
                        'Access denied to query criteria for '
                        . $this->buildResourceName($targetModel, $action)
                    );
                }
            }

            if (!$this->isFieldActionAllowed($targetModel, $field, 'get')) {
                $this->denyAccess(
                    'Access denied to query criteria for '
                    . $this->buildFieldResourceName($targetModel, $field, 'get')
                );
            }
        }
    }

    /**
     * Whether the user may perform a field action: module action prerequisite
     * plus `{module}.{field}.{action}`.
     *
     * @param  string $modelName
     * @param  string $field
     * @param  string $action get|create|update
     * @return bool
     */
    public function isFieldActionAllowed($modelName, $field, $action)
    {
        if ($this->isStructuralField($modelName, $field)) {
            return true;
        }

        if (!$this->isModelActionAllowed($modelName, $action)) {
            return false;
        }

        if ($this->isResourceAllowed(
            $this->buildFieldResourceName($modelName, $field, $action)
        )) {
            return true;
        }

        // Write + Read combo: an explicit field write grant implies read.
        // Missing write rows must not promote a denied/missing get (D-011).
        if ($action === 'get' && $this->isFieldWriteExplicitlyAllowed($modelName, $field)) {
            return true;
        }

        return false;
    }

    /**
     * Whether create or update for this field is explicitly allowed (allowed=1).
     * Missing rows do not count — only stored allow grants.
     *
     * @param  string $modelName
     * @param  string $field
     * @return bool
     */
    protected function isFieldWriteExplicitlyAllowed($modelName, $field)
    {
        foreach (['create', 'update'] as $writeAction) {
            $resourceName = $this->buildFieldResourceName($modelName, $field, $writeAction);
            if (!Permission::hasResource($resourceName)) {
                continue;
            }

            foreach (Permission::getPermissionsForResource($resourceName) as $permission) {
                if ($this->normalizeFlag($permission['allowed'] ?? null) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Drop denied SELECT columns; always keep id and linkage/join FKs.
     *
     * Column entries may be `Model.field`, `rel.field`, or `… AS alias`.
     *
     * @param  string $modelAlias Base model alias for the query
     * @param  array  $fields
     * @param  string $action
     * @return array
     */
    public function filterAuthorizedFields($modelAlias, array $fields, $action = 'get')
    {
        $filtered = [];

        foreach ($fields as $column) {
            $parsed = $this->parseSelectColumn($column);
            if ($parsed === null) {
                $filtered[] = $column;
                continue;
            }

            $targetModel = $this->resolveColumnModel($modelAlias, $parsed['alias']);
            if ($targetModel === null) {
                $filtered[] = $column;
                continue;
            }

            if ($this->isFieldActionAllowed($targetModel, $parsed['field'], $action)) {
                $filtered[] = $column;
            }
        }

        return array_values($filtered);
    }

    /**
     * Filter writable attributes in a create/update payload.
     *
     * Denied field attributes are discarded (not written). HTTP 403 is reserved
     * for module-level action denial, not field denial on write.
     *
     * @param  string $modelName
     * @param  array  $payload
     * @param  string $action create|update
     * @return array Payload with unauthorized fields removed
     */
    public function authorizeWritableFields($modelName, array $payload, $action)
    {
        $filtered = [];

        foreach ($payload as $field => $value) {
            if ($field === 'id' || $this->isStructuralField($modelName, $field)) {
                $filtered[$field] = $value;
                continue;
            }

            // Nested relationship payloads are not scalar field attrs.
            if (is_array($value)) {
                $filtered[$field] = $value;
                continue;
            }

            if ($this->isFieldActionAllowed($modelName, $field, $action)) {
                $filtered[$field] = $value;
            }
            // else discard denied field — do not 403
        }

        return $filtered;
    }

    /**
     * Whether the user may perform an action on a model, including all of its
     * authorization groups (each group requires `{group}.get`).
     *
     * @param  string $modelName
     * @param  string $action
     * @return bool
     */
    public function isModelActionAllowed($modelName, $action)
    {
        if (!$this->isResourceAllowed($this->buildResourceName($modelName, $action))) {
            return false;
        }

        foreach ($this->getAuthorizationGroups($modelName) as $groupModel) {
            if (!$this->isResourceAllowed($this->buildResourceName($groupModel, 'get'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Authorize a model action (and its groups), or throw if denied.
     *
     * @param  string $modelName
     * @param  string $action
     * @return bool
     * @throws \Gaia\Exception\Access
     */
    public function checkModelAccess($modelName, $action)
    {
        if ($this->isModelActionAllowed($modelName, $action)) {
            return true;
        }

        $this->denyAccess(
            'Access denied to ' . $this->buildResourceName($modelName, $action)
        );
    }

    /**
     * Authorization group model names declared on the given model.
     *
     * @param  string $modelName
     * @return array
     */
    protected function getAuthorizationGroups($modelName)
    {
        return $this->di->get('metaManager')->getModelGroups($modelName);
    }

    /**
     * Extract the action segment from a resource name (e.g. issue.get → get).
     *
     * @param  string $resourceName
     * @return string
     */
    protected function extractActionFromResourceName($resourceName)
    {
        $parts = explode('.', $resourceName, 2);
        return isset($parts[1]) ? $parts[1] : $resourceName;
    }

    /**
     * Log the denial detail and throw a generic access exception for the client.
     *
     * @param  string $detail
     * @return void
     * @throws \Gaia\Exception\Access
     */
    protected function denyAccess($detail)
    {
        global $logger;

        if (isset($logger)) {
            $logger->warning($detail);
        }

        throw new \Gaia\Exception\Access('Access Denied');
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
     * Base-model fields appear as bare names (`subject`). Related fields use
     * relationship alias qualification (`project.name`). Legacy
     * `Model.field` against the base model alias is also accepted.
     *
     * @param  array  $clauses
     * @param  string $modelAlias Base model alias for bare field names
     * @return array
     */
    protected function getClauseFieldReferences(array $clauses, $modelAlias)
    {
        $references = [];
        $query = isset($clauses['query']) ? $clauses['query'] : '';

        if (is_string($query) && $query !== '') {
            $this->appendQueryClauseFieldReferences($references, $query, $modelAlias);
        }

        if (isset($clauses['having']) && $clauses['having'] !== '') {
            $having = is_array($clauses['having'])
                ? implode(' ', $clauses['having'])
                : (string) $clauses['having'];
            $this->appendQueryClauseFieldReferences($references, $having, $modelAlias);
        }

        foreach (['sort', 'groupBy'] as $clauseName) {
            if (!isset($clauses[$clauseName]) || $clauses[$clauseName] === '') {
                continue;
            }

            $clause = is_array($clauses[$clauseName])
                ? implode(',', $clauses[$clauseName])
                : (string) $clauses[$clauseName];

            foreach (preg_split('/\s*,\s*/', $clause) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $part = ltrim($part, '-+');
                $part = preg_replace('/\s+(ASC|DESC)$/i', '', $part);
                if (!preg_match(
                    '/^([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)/',
                    $part,
                    $match
                )) {
                    continue;
                }

                $this->appendClauseFieldToken($references, $match[1], $modelAlias);
            }
        }

        return array_values($references);
    }

    /**
     * Parse parenthesized query/having substatements into field references.
     *
     * @param  array  $references
     * @param  string $statement
     * @param  string $modelAlias
     * @return void
     */
    protected function appendQueryClauseFieldReferences(&$references, $statement, $modelAlias)
    {
        if (!preg_match_all('@\(([^(]*?)\)@', $statement, $subMatches)) {
            return;
        }

        foreach ($subMatches[1] as $inner) {
            $inner = trim($inner);
            if ($inner === '') {
                continue;
            }

            // First token is the field: bare `subject` or qualified `rel.field`
            if (!preg_match(
                '/^([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)/',
                $inner,
                $match
            )) {
                continue;
            }

            $this->appendClauseFieldToken($references, $match[1], $modelAlias);
        }
    }

    /**
     * Add a unique alias/field reference from a clause field token.
     *
     * @param  array  $references
     * @param  string $token Bare field or alias.field
     * @param  string $modelAlias
     * @return void
     */
    protected function appendClauseFieldToken(&$references, $token, $modelAlias)
    {
        if (strpos($token, '.') !== false) {
            list($alias, $field) = explode('.', $token, 2);
        } else {
            $alias = $modelAlias;
            $field = $token;
        }

        $key = $alias . '.' . $field;
        $references[$key] = [
            'alias' => $alias,
            'field' => $field,
        ];
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

        $this->denyAccess("Access denied to {$resourceName}");
    }

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
     * Apply ACL on model/related model fields using `{module}.{field}.get`.
     * Only fields the user can access are kept for the API response allow-list.
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
     * Apply ACL on a scalar field using action-based field identity.
     *
     * @param  string $fieldName
     * @param  string $modelAlias
     * @param  array  $params
     * @param  array  $fields
     * @return void
     */
    protected function applyACLByScalarField($fieldName, $modelAlias, $params, &$fields)
    {
        $targetModel = $modelAlias;
        $targetField = $fieldName;
        $allowedField = $fieldName;
        $aliasByFields = $this->getAliasByFields($params);

        if (in_array($fieldName, array_keys($aliasByFields))) {
            list($moduleName, $moduleField) = explode('.', $aliasByFields[$fieldName]);
            $isModel = $modelAlias === $moduleName;

            if (!$isModel) {
                $relName = $moduleName;
                $moduleName = $this->getRelatedModelName($modelAlias, $relName);
            }
            $targetModel = $moduleName;
            $targetField = $moduleField;
            $allowedField = $fieldName;
        }

        if ($this->isFieldActionAllowed($targetModel, $targetField, 'get')) {
            $fields[$allowedField] = $allowedField;
        }
    }

    /**
     * Apply ACL on an object (related) field set.
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
        $relatedModelName = $isModel ? $modelAlias : $this->getRelatedModelName($modelAlias, $fieldName);

        foreach (array_keys($values) as $nestedField) {
            $allowedField = "{$fieldName}.{$nestedField}";
            if ($this->isFieldActionAllowed($relatedModelName, $nestedField, 'get')) {
                $fields[] = $allowedField;
            }
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
     * Build `{module}.{field}.{action}` resource name.
     *
     * @param  string $modelName
     * @param  string $field
     * @param  string $action
     * @return string
     */
    protected function buildFieldResourceName($modelName, $field, $action)
    {
        return strtolower($modelName) . '.' . $field . '.' . $action;
    }

    /**
     * Parse a SELECT column into alias + field (strips AS aliases).
     *
     * @param  string $column
     * @return array|null {alias, field} or null if not Model.field form
     */
    protected function parseSelectColumn($column)
    {
        $expression = trim((string) $column);
        if ($expression === '') {
            return null;
        }

        if (preg_match('/\s+AS\s+/i', $expression)) {
            $parts = preg_split('/\s+AS\s+/i', $expression, 2);
            $expression = trim($parts[0]);
        }

        // Skip aggregates / expressions without a simple Alias.field form.
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)$/', $expression, $matches)) {
            return null;
        }

        return [
            'alias' => $matches[1],
            'field' => $matches[2],
        ];
    }

    /**
     * Resolve a column alias (base model or relationship name) to a model name.
     *
     * @param  string $modelAlias
     * @param  string $columnAlias
     * @return string|null
     */
    protected function resolveColumnModel($modelAlias, $columnAlias)
    {
        if ($columnAlias === $modelAlias) {
            return $modelAlias;
        }

        $relMeta = $this->di->get('metaManager')->getRelationshipMeta(
            $modelAlias,
            $columnAlias,
            false
        );

        if (empty($relMeta)) {
            return null;
        }

        return $this->getRelatedModelName($modelAlias, $columnAlias);
    }

    /**
     * Whether a field always bypasses field ACL (id / linkage FKs).
     *
     * Shared rules with AclMapCatalog so catalog exclusions and runtime
     * bypass stay aligned (D-035).
     *
     * @param  string $modelName
     * @param  string $field
     * @return bool
     */
    protected function isStructuralField($modelName, $field)
    {
        if ($field === 'id' || AclMapCatalog::isLinkageIdFieldName($field)) {
            return true;
        }

        try {
            $meta = $this->di->get('metaManager')->getModelMeta($modelName);
        } catch (\Throwable $e) {
            return false;
        }

        return AclMapCatalog::isStructuralFieldName($field, $meta);
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
