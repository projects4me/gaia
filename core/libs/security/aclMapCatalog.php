<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * Builds module/action and module/field ACL catalogs by inspecting REST controllers
 * and model metadata.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Libraries\Security
 */
class AclMapCatalog
{
    /**
     * Field-level ACL actions (no field delete).
     *
     * @var array
     */
    public const FIELD_ACTIONS = ['get', 'create', 'update'];

    /**
     * Build module actions from v1 REST routes and controller aclMaps.
     *
     * Uses each controller's effective `$aclMap` (own property or inherited
     * from RestController). Controllers that redefine `$aclMap` fully own
     * their catalog; everyone else inherits the base map.
     *
     * @return array
     */
    public static function buildModuleActions()
    {
        $modules = [];

        foreach (self::getAclAllowedModules() as $moduleName => $controllerClass) {
            $actions = [];
            foreach (self::getControllerAclMap($controllerClass) as $map) {
                $action = self::resolveAclCatalogAction($map);
                if ($action === null) {
                    continue;
                }

                $actions[] = [
                    'action' => $action,
                    'resourceName' => $moduleName . '.' . $action,
                ];
            }

            $modules[] = [
                'module' => $moduleName,
                'actions' => array_values(array_unique($actions, SORT_REGULAR)),
            ];
        }

        return $modules;
    }

    /**
     * Build field resources from model metadata for ACL-allowed modules.
     *
     * Emits get/create/update for business attributes only:
     * `{module}.{field}.{action}`.
     *
     * Excludes (from metadata):
     * - structural linkage (`getStructuralFieldNames`)
     * - `acl => false`
     * - `secure => true` (credential masking, not ACL)
     * - `linkedTo` derived/display fields
     *
     * @param  \Gaia\Libraries\Meta\Manager $metaManager
     * @return array
     */
    public static function buildModuleFields($metaManager)
    {
        $modules = [];

        foreach (self::getAclAllowedModules() as $moduleName => $controllerClass) {
            $modelName = self::camelize($moduleName);
            try {
                $meta = $metaManager->getModelMeta($modelName);
            } catch (\Throwable $e) {
                continue;
            }

            if (empty($meta['fields']) || !is_array($meta['fields'])) {
                continue;
            }

            $structuralKeys = self::getStructuralFieldNames($meta);
            $fields = [];

            foreach ($meta['fields'] as $fieldName => $fieldMeta) {
                if (!self::isFieldCatalogEligible($fieldName, $fieldMeta, $structuralKeys)) {
                    continue;
                }

                $actions = [];
                foreach (self::FIELD_ACTIONS as $action) {
                    $actions[] = [
                        'action' => $action,
                        'resourceName' => $moduleName . '.' . $fieldName . '.' . $action,
                    ];
                }

                $fields[] = [
                    'field' => $fieldName,
                    'actions' => $actions,
                ];
            }

            if (empty($fields)) {
                continue;
            }

            $modules[] = [
                'module' => $moduleName,
                'fields' => $fields,
            ];
        }

        return $modules;
    }

    /**
     * Whether a field should appear in the field-ACL catalog (business attribute).
     *
     * @param  string $fieldName
     * @param  array  $fieldMeta
     * @param  array  $structuralKeys FK / id names for this model
     * @return bool
     */
    public static function isFieldCatalogEligible($fieldName, $fieldMeta, array $structuralKeys)
    {
        if (!is_array($fieldMeta)) {
            return false;
        }

        if (isset($structuralKeys[$fieldName])) {
            return false;
        }

        if (isset($fieldMeta['acl']) && $fieldMeta['acl'] === false) {
            return false;
        }

        if (!empty($fieldMeta['secure'])) {
            return false;
        }

        if (isset($fieldMeta['linkedTo']) && $fieldMeta['linkedTo'] !== '' && $fieldMeta['linkedTo'] !== null) {
            return false;
        }

        return true;
    }

    /**
     * Whether a field is structural linkage (bypasses field ACL / omitted from catalog).
     *
     * @param  string $fieldName
     * @param  array  $meta Model metadata
     * @return bool
     */
    public static function isStructuralFieldName($fieldName, array $meta)
    {
        $keys = self::getStructuralFieldNames($meta);
        return isset($keys[$fieldName]);
    }

    /**
     * Collect structural field names that must stay out of field ACL.
     *
     * Includes:
     * - `id`
     * - fields marked `identifier` / `relatedIdentifier`
     * - belongsTo / hasOne `primaryKey` FKs (even when still named `id` on rare models)
     * - linkage naming convention: fields ending in `Id` (e.g. roleId, projectId)
     *
     * @param  array $meta Model metadata
     * @return array keyed by field name => true
     */
    public static function getStructuralFieldNames(array $meta)
    {
        $keys = ['id' => true];

        if (!empty($meta['fields']) && is_array($meta['fields'])) {
            foreach ($meta['fields'] as $fieldName => $fieldMeta) {
                if (!is_array($fieldMeta)) {
                    continue;
                }

                if (!empty($fieldMeta['identifier']) || !empty($fieldMeta['relatedIdentifier'])) {
                    $keys[$fieldName] = true;
                    continue;
                }

                if (self::isLinkageIdFieldName($fieldName)) {
                    $keys[$fieldName] = true;
                }
            }
        }

        if (empty($meta['relationships']) || !is_array($meta['relationships'])) {
            return $keys;
        }

        foreach ($meta['relationships'] as $relationshipType => $related) {
            if ($relationshipType !== 'belongsTo' && $relationshipType !== 'hasOne') {
                continue;
            }
            if (!is_array($related)) {
                continue;
            }
            foreach ($related as $relDef) {
                if (!empty($relDef['primaryKey']) && $relDef['primaryKey'] !== 'id') {
                    $keys[$relDef['primaryKey']] = true;
                }
            }
        }

        return $keys;
    }

    /**
     * Linkage / foreign-key style attribute names (…Id).
     *
     * @param  string $fieldName
     * @return bool
     */
    public static function isLinkageIdFieldName($fieldName)
    {
        return (bool) preg_match('/Id$/', (string) $fieldName);
    }

    /**
     * ACL-allowed REST modules keyed by module name → controller class.
     *
     * @return array
     */
    private static function getAclAllowedModules()
    {
        $routesConfig = include APP_PATH . '/core/config/routes.php';
        $restModules = isset($routesConfig['routes']['rest']['v1']) ? $routesConfig['routes']['rest']['v1'] : [];
        $modules = [];

        foreach ($restModules as $moduleName => $routeConfig) {
            $controllerClass = 'Gaia\\MVC\\REST\\Controllers\\' . self::camelize($moduleName) . 'Controller';
            if (!self::isControllerAclAllowed($controllerClass)) {
                continue;
            }
            $modules[$moduleName] = $controllerClass;
        }

        return $modules;
    }

    /**
     * Get the effective aclMap for a controller class.
     *
     * Reflection returns the child's `$aclMap` when declared, otherwise the
     * inherited RestController defaults.
     *
     * @param  string $className
     * @return array
     */
    private static function getControllerAclMap($className)
    {
        if (!class_exists($className)) {
            return [];
        }

        $reflection = new \ReflectionClass($className);
        $defaults = $reflection->getDefaultProperties();
        return (isset($defaults['aclMap']) && is_array($defaults['aclMap'])) ? $defaults['aclMap'] : [];
    }

    /**
     * Whether a controller participates in action-based ACL catalog.
     *
     * Controllers with $aclAllowed = false or $authorization = false are excluded.
     * Missing controller class is treated as not ACL-managed.
     *
     * @param  string $className
     * @return bool
     */
    private static function isControllerAclAllowed($className)
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);
        $defaults = $reflection->getDefaultProperties();

        if (isset($defaults['authorization']) && $defaults['authorization'] === false) {
            return false;
        }

        return !isset($defaults['aclAllowed']) || $defaults['aclAllowed'] === true;
    }

    /**
     * Resolve the ACL action name from an aclMap entry.
     *
     * @param  array $map
     * @return string|null
     */
    private static function resolveAclCatalogAction(array $map)
    {
        return isset($map['action']) ? $map['action'] : null;
    }

    /**
     * Convert snake_case to CamelCase.
     *
     * @param  string $value
     * @return string
     */
    private static function camelize($value)
    {
        $segments = explode('_', (string) $value);
        $segments = array_map(function ($segment) {
            return ucfirst(strtolower($segment));
        }, $segments);
        return implode('', $segments);
    }
}
