<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

/**
 * Extracts the model behaviours in `core/mvc/models/behaviors/`. The models that
 * declare a behaviour come from their metadata (MetadataModelExtractor).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BehaviourExtractor
{
    public const SOURCE = 'core/mvc/models/behaviors';

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
     * @return array[] per behaviour: name (as listed in model metadata), class, file, hooks (public methods)
     */
    public function extract(): array
    {
        $files = glob($this->root . '/' . self::SOURCE . '/*.php') ?: [];
        sort($files, SORT_STRING);

        $records = [];
        foreach ($files as $file) {
            $statements = PhpSource::parse($file);
            $namespace = PhpSource::namespaceOf($statements);
            foreach (PhpSource::classes($statements) as $class) {
                $records[] = [
                    'name' => $class->name->toString(),
                    'class' => ltrim($namespace . '\\' . $class->name->toString(), '\\'),
                    'file' => self::SOURCE . '/' . basename($file),
                    'hooks' => PhpSource::publicMethods($class),
                ];
            }
        }

        return $records;
    }
}
