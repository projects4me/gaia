<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Extracts the permission rules that live in code rather than in the database:
 * the record scopes and resolution modes of `core/libs/security/Acl.php`, the
 * resources it treats as scoped by default, and the map from a REST action to
 * the permission action it needs (`RestController::$aclMap`: `post` needs `create`).
 *
 * Which role holds which permission is data (the permissions table); the
 * harness profiles carry the test roles (ProfileExtractor).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclExtractor
{
    public const ACL = 'core/libs/security/Acl.php';
    public const REST_CONTROLLER = 'core/mvc/controllers/RestController.php';

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return array with the keys
     *  file, scopes (name => value, from the SCOPE_* constants), resolutionModes (RESOLUTION_* values),
     *  scopedResources (default), methods (public API) and actions (REST action => permission action)
     */
    public function extract(): array
    {
        $acl = $this->classIn(self::ACL, 'Acl');
        $constants = PhpSource::constants($acl);

        return [
            'file' => self::ACL,
            'scopes' => $this->prefixed($constants, 'SCOPE_'),
            'resolutionModes' => array_values($this->prefixed($constants, 'RESOLUTION_')),
            'scopedResources' => $this->scopedResources($acl),
            'methods' => PhpSource::publicMethods($acl),
            'actions' => $this->actionMap(),
        ];
    }

    /**
     * @param string $file
     * @param string $name
     * @return Node\Stmt\ClassLike
     */
    private function classIn(string $file, string $name): Node\Stmt\ClassLike
    {
        foreach (PhpSource::classes(PhpSource::parse($this->root . '/' . $file)) as $class) {
            if ($class->name->toString() === $name) {
                return $class;
            }
        }

        throw new \RuntimeException("{$file} does not declare class {$name}");
    }

    /**
     * @param array $constants
     * @param string $prefix
     * @return array lowercase name without the prefix => value
     */
    private function prefixed(array $constants, string $prefix): array
    {
        $result = [];
        foreach ($constants as $name => $value) {
            if (strpos($name, $prefix) === 0) {
                $result[strtolower(substr($name, strlen($prefix)))] = $value;
            }
        }

        return $result;
    }

    /**
     * The array `isScopedResource()` starts from, before system settings override it.
     *
     * @param Node\Stmt\ClassLike $acl
     * @return string[]
     */
    private function scopedResources(Node\Stmt\ClassLike $acl): array
    {
        $method = $acl->getMethod('isScopedResource');
        if ($method !== null) {
            foreach ((new NodeFinder())->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
                if ($assign->var instanceof Node\Expr\Variable && $assign->var->name === 'scoped'
                    && $assign->expr instanceof Node\Expr\Array_) {
                    return array_values(PhpSource::literal($assign->expr));
                }
            }
        }

        return [];
    }

    /**
     * @return string[] REST action => permission action
     */
    private function actionMap(): array
    {
        $map = PhpSource::propertyDefault($this->classIn(self::REST_CONTROLLER, 'RestController'), 'aclMap');
        $actions = [];
        foreach (is_array($map) ? $map : [] as $action => $entry) {
            if (isset($entry['action'])) {
                $actions[$action] = $entry['action'];
            }
        }

        return $actions;
    }
}
