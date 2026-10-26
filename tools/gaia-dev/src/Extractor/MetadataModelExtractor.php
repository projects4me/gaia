<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

/**
 * Extracts the model definitions from `app/metadata/model/*.php`: fields,
 * relationships, behaviours and ACL settings, one record per model.
 *
 * Relationship targets are reduced from class names
 * (`\Gaia\MVC\Models\User`) to model names (`User`).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MetadataModelExtractor
{
    public const SOURCE_DIR = 'app/metadata/model';

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
     * @return array[] model records sorted by name, each with the keys name,
     *  file, tableName, isView, fields, relationships, behaviors and acl
     */
    public function extract(): array
    {
        $records = [];
        $files = glob($this->root . '/' . self::SOURCE_DIR . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $relative = self::SOURCE_DIR . '/' . basename($file);
            foreach (SourceFile::readPhpArray($file) as $name => $definition) {
                $records[$name] = $this->toRecord($name, $relative, $definition);
            }
        }

        ksort($records);

        return array_values($records);
    }

    /**
     * @param string $name
     * @param string $file
     * @param array $definition
     * @return array
     */
    private function toRecord(string $name, string $file, array $definition): array
    {
        return [
            'name' => $name,
            'file' => $file,
            'tableName' => isset($definition['tableName']) ? $definition['tableName'] : null,
            'isView' => !empty($definition['isView']),
            'fields' => $this->fields($definition),
            'relationships' => $this->relationships($definition),
            'behaviors' => isset($definition['behaviors']) ? array_values($definition['behaviors']) : [],
            'acl' => isset($definition['acl']) ? $definition['acl'] : null,
        ];
    }

    /**
     * Fields keep every metadata attribute except the name, which becomes the key.
     *
     * @param array $definition
     * @return array[]
     */
    private function fields(array $definition): array
    {
        $fields = [];
        $source = isset($definition['fields']) ? $definition['fields'] : [];

        foreach ($source as $key => $field) {
            $name = isset($field['name']) ? $field['name'] : $key;
            unset($field['name']);
            $fields[] = ['name' => $name, 'attributes' => $field];
        }

        return $fields;
    }

    /**
     * @param array $definition
     * @return array[] relationships with name, kind, relatedModel,
     *  secondaryModel (many-to-many only) and the remaining keys as attributes
     */
    private function relationships(array $definition): array
    {
        $relationships = [];
        $source = isset($definition['relationships']) ? $definition['relationships'] : [];

        foreach ($source as $kind => $byName) {
            foreach ($byName as $name => $relationship) {
                $relatedModel = isset($relationship['relatedModel'])
                    ? self::modelName($relationship['relatedModel']) : null;
                $secondaryModel = isset($relationship['secondaryModel'])
                    ? self::modelName($relationship['secondaryModel']) : null;
                unset($relationship['relatedModel'], $relationship['secondaryModel']);

                $relationships[] = [
                    'name' => $name,
                    'kind' => $kind,
                    'relatedModel' => $relatedModel,
                    'secondaryModel' => $secondaryModel,
                    'attributes' => $relationship,
                ];
            }
        }

        return $relationships;
    }

    /**
     * @param string $class e.g. `\Gaia\MVC\Models\User`
     * @return string e.g. `User`
     */
    private static function modelName(string $class): string
    {
        $parts = explode('\\', trim($class, '\\'));

        return end($parts);
    }
}
