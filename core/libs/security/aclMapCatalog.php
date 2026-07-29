<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * Builds module/action ACL catalogs by inspecting REST controllers.
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Libraries\Security
 */
class AclMapCatalog
{
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
        $routesConfig = include APP_PATH . '/core/config/routes.php';
        $restModules = isset($routesConfig['routes']['rest']['v1']) ? $routesConfig['routes']['rest']['v1'] : [];
        $modules = [];

        foreach ($restModules as $moduleName => $routeConfig) {
            $controllerClass = 'Gaia\\MVC\\REST\\Controllers\\' . self::camelize($moduleName) . 'Controller';
            if (!self::isControllerAclAllowed($controllerClass)) {
                continue;
            }

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
