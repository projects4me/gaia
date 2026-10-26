<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

use PhpParser\Node\Stmt\Class_;

/**
 * Extracts the controllers in `app/api/v1/controllers/`: their actions (the
 * public `<name>Action` methods) and the components in `$uses`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ControllerExtractor
{
    public const SOURCE = 'app/api/v1/controllers';

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
     * @return array[] per controller: name (lowercase, as in the routes), class, file, extends, uses, actions
     */
    public function extract(): array
    {
        $files = glob($this->root . '/' . self::SOURCE . '/*Controller.php') ?: [];
        sort($files, SORT_STRING);

        $records = [];
        foreach ($files as $file) {
            $statements = PhpSource::parse($file);
            foreach (PhpSource::classes($statements) as $class) {
                if (!$class instanceof Class_) {
                    continue;
                }
                $short = $class->name->toString();
                $uses = PhpSource::propertyDefault($class, 'uses');
                $records[] = [
                    'name' => strtolower(preg_replace('/Controller$/', '', $short)),
                    'class' => ltrim(PhpSource::namespaceOf($statements) . '\\' . $short, '\\'),
                    'file' => self::SOURCE . '/' . basename($file),
                    'extends' => PhpSource::nameToString($class->extends),
                    'uses' => is_array($uses) ? array_values($uses) : [],
                    'actions' => $this->actions($class),
                ];
            }
        }

        return $records;
    }

    /**
     * @param Class_ $class
     * @return string[] action names (`getAction` becomes `get`)
     */
    private function actions(Class_ $class): array
    {
        $actions = [];
        foreach (PhpSource::publicMethods($class) as $method) {
            if (substr($method, -6) === 'Action' && $method !== 'Action') {
                $actions[] = substr($method, 0, -6);
            }
        }

        return $actions;
    }
}
