<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

/**
 * Checks a decoded JSON value against a JSON Schema, for the keywords Gaia's
 * own schemas use: type (one or a list), const, required, properties,
 * additionalProperties (false), items, minimum and minLength. Any other
 * keyword is reported, so a schema can't silently rely on one this doesn't check.
 *
 * Gaia ships no JSON Schema library; this keeps the tests dependency-free.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class JsonSchemaSubset
{
    private const ANNOTATIONS = ['$schema', '$id', 'title', 'description'];

    private const KEYWORDS = [
        'type', 'const', 'required', 'properties', 'additionalProperties', 'items', 'minimum', 'minLength',
    ];

    /**
     * Load a schema file as decoded JSON (objects as stdClass).
     *
     * @param  string $path
     * @return object
     */
    public static function load($path)
    {
        $schema = json_decode(file_get_contents($path));
        if (!is_object($schema)) {
            throw new \InvalidArgumentException("Not a JSON Schema: {$path}");
        }

        return $schema;
    }

    /**
     * The violations of the value against the schema, empty when it conforms.
     *
     * @param  mixed  $value  Decoded with json_decode($json) (objects as stdClass)
     * @param  object $schema
     * @param  string $path
     * @return string[]
     */
    public static function violations($value, $schema, $path = '$')
    {
        $errors = [];
        foreach (array_keys(get_object_vars($schema)) as $keyword) {
            if (!in_array($keyword, self::KEYWORDS, true) && !in_array($keyword, self::ANNOTATIONS, true)) {
                $errors[] = "{$path}: unsupported schema keyword '{$keyword}'";
            }
        }

        if (property_exists($schema, 'const') && $value !== $schema->const) {
            $errors[] = "{$path}: expected " . json_encode($schema->const) . ', got ' . json_encode($value);
        }

        if (isset($schema->type)) {
            $types = (array) $schema->type;
            if (!in_array(self::typeOf($value), $types, true)
                && !(self::typeOf($value) === 'integer' && in_array('number', $types, true))
            ) {
                return array_merge($errors, ["{$path}: expected " . implode('|', $types) . ', got ' . self::typeOf($value)]);
            }
        }

        if (is_object($value)) {
            $errors = array_merge($errors, self::objectViolations($value, $schema, $path));
        }
        if (is_array($value) && isset($schema->items)) {
            foreach ($value as $index => $item) {
                $errors = array_merge($errors, self::violations($item, $schema->items, "{$path}[{$index}]"));
            }
        }
        if (is_int($value) && isset($schema->minimum) && $value < $schema->minimum) {
            $errors[] = "{$path}: {$value} is below the minimum {$schema->minimum}";
        }
        if (is_string($value) && isset($schema->minLength) && mb_strlen($value) < $schema->minLength) {
            $errors[] = "{$path}: shorter than {$schema->minLength}";
        }

        return $errors;
    }

    /**
     * @param  object $value
     * @param  object $schema
     * @param  string $path
     * @return string[]
     */
    private static function objectViolations($value, $schema, $path)
    {
        $errors = [];
        $properties = isset($schema->properties) ? get_object_vars($schema->properties) : [];

        foreach (isset($schema->required) ? $schema->required : [] as $name) {
            if (!property_exists($value, $name)) {
                $errors[] = "{$path}: missing '{$name}'";
            }
        }

        foreach (get_object_vars($value) as $name => $propertyValue) {
            if (isset($properties[$name])) {
                $errors = array_merge($errors, self::violations($propertyValue, $properties[$name], "{$path}.{$name}"));
            } elseif (isset($schema->additionalProperties) && $schema->additionalProperties === false) {
                $errors[] = "{$path}: unexpected '{$name}'";
            }
        }

        return $errors;
    }

    /**
     * @param  mixed $value
     * @return string The JSON Schema type name
     */
    private static function typeOf($value)
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_array($value)) {
            return 'array';
        }

        return 'object';
    }
}
