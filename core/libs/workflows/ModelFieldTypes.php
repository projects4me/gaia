<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The fields of a model and their types as the workflow editor sees them
 * (string, number, boolean, date, datetime), read from the model metadata
 * in app/metadata/model, the source of truth for a model's columns.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelFieldTypes
{
    /**
     * Column types by editor type.
     *
     * @var array
     */
    private const TYPES = [
        'varchar' => 'string',
        'char' => 'string',
        'text' => 'string',
        'mediumtext' => 'string',
        'int' => 'number',
        'float' => 'number',
        'bool' => 'boolean',
        'date' => 'date',
        'datetime' => 'datetime',
    ];

    /**
     * Columns that are not part of what a record is.
     *
     * @var string[]
     */
    private const HIDDEN = ['deleted'];

    /**
     * The model's fields: name => editor type, in metadata order.
     *
     * @param  string $model Model name, e.g. `Issue`.
     * @return array
     */
    public function forModel($model)
    {
        return self::fromMetadata($this->metadataFields($model));
    }

    /**
     * Editor types for metadata field definitions. Columns of a type the
     * editor has no use for are left out.
     *
     * @param  array $fields name => ['type' => <column type>, ...]
     * @return array
     */
    public static function fromMetadata(array $fields)
    {
        $types = [];
        foreach ($fields as $name => $field) {
            $type = is_array($field) && isset($field['type']) ? $field['type'] : null;
            if (in_array($name, self::HIDDEN, true) || !is_string($type) || !isset(self::TYPES[$type])) {
                continue;
            }
            $types[$name] = self::TYPES[$type];
        }

        return $types;
    }

    /**
     * The `fields` section of the model's metadata file.
     *
     * @param  string $model
     * @return array
     */
    protected function metadataFields($model)
    {
        if (!preg_match('/^[A-Z][A-Za-z]*$/', (string) $model)) {
            return [];
        }
        $path = APP_PATH . '/app/metadata/model/' . $model . '.php';
        if (!is_file($path)) {
            return [];
        }
        $models = [];
        $loaded = include $path;
        $metadata = is_array($loaded) ? $loaded : $models;

        return isset($metadata[$model]['fields']) ? $metadata[$model]['fields'] : [];
    }
}
