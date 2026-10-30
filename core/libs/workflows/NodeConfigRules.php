<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Checks a workflow node's config against the fields its kind takes (see
 * `NodeKindCatalog`): required fields are present, each value has its
 * field's type, a referenced user or agent exists, and no unknown field is
 * sent.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NodeConfigRules
{
    /**
     * Operators a filter or switch condition may use.
     */
    public const OPERATORS = ['equals', 'notEquals', 'contains', 'in', 'notIn', 'empty', 'notEmpty', 'gt', 'lt'];

    /**
     * @var NodeKindCatalog
     */
    private $catalog;

    /**
     * @param NodeKindCatalog|null $catalog
     */
    public function __construct(NodeKindCatalog $catalog = null)
    {
        $this->catalog = $catalog ?: new NodeKindCatalog();
    }

    /**
     * The problems with the config, empty when it is valid.
     *
     * @param  string $category
     * @param  string $kind
     * @param  array  $config
     * @return string[]
     */
    public function errors($category, $kind, array $config)
    {
        $fields = $this->catalog->configFields($category, $kind);
        $errors = [];

        foreach (array_keys($config) as $name) {
            if (!isset($fields[$name])) {
                $errors[] = "config.{$name} is not a setting of {$kind}.";
            }
        }

        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $config) || $config[$name] === null || $config[$name] === '') {
                if ($field['required']) {
                    $errors[] = "config.{$name} is required for {$kind}.";
                }
                continue;
            }
            $error = $this->valueError($name, $field['type'], $config[$name]);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param  string $name
     * @param  string $type
     * @param  mixed  $value
     * @return string|null
     */
    private function valueError($name, $type, $value)
    {
        $model = NodeKindCatalog::refModel($type);
        if ($model !== null) {
            return $this->refError($name, $model, $value);
        }
        if ($type === 'conditions') {
            return $this->conditionsError("config.{$name}", $value);
        }
        if ($type === 'cases') {
            return $this->casesError($name, $value);
        }

        return $this->isText($value) ? null : "config.{$name} must be a non-empty string.";
    }

    /**
     * @param  string $name
     * @param  string $model
     * @param  mixed  $value
     * @return string|null
     */
    private function refError($name, $model, $value)
    {
        if (!$this->isText($value)) {
            return "config.{$name} must be the id of a {$model} or an expression.";
        }
        if (NodeKindCatalog::isExpression($value) || $this->exists($model, trim($value))) {
            return null;
        }

        return "config.{$name} must name an existing {$model}.";
    }

    /**
     * @param  string $path
     * @param  mixed  $value
     * @return string|null
     */
    private function conditionsError($path, $value)
    {
        if (!$this->isList($value) || $value === []) {
            return "{$path} must be a non-empty list of conditions.";
        }
        foreach ($value as $i => $condition) {
            if (!is_array($condition) || !isset($condition['field']) || !$this->isText($condition['field'])) {
                return "{$path}[{$i}].field is required.";
            }
            if (!isset($condition['operator']) || !in_array($condition['operator'], self::OPERATORS, true)) {
                return "{$path}[{$i}].operator must be one of " . implode(', ', self::OPERATORS) . '.';
            }
        }

        return null;
    }

    /**
     * @param  string $name
     * @param  mixed  $value
     * @return string|null
     */
    private function casesError($name, $value)
    {
        if (!$this->isList($value) || $value === []) {
            return "config.{$name} must be a non-empty list of cases.";
        }
        $keys = [];
        foreach ($value as $i => $case) {
            if (!is_array($case) || !isset($case['key']) || !$this->isText($case['key'])) {
                return "config.{$name}[{$i}].key is required.";
            }
            if ($case['key'] === NodeKindCatalog::DEFAULT_PORT || in_array($case['key'], $keys, true)) {
                return "config.{$name}[{$i}].key must be unique and not '" . NodeKindCatalog::DEFAULT_PORT . "'.";
            }
            $keys[] = $case['key'];
            $error = $this->conditionsError("config.{$name}[{$i}].conditions", isset($case['conditions']) ? $case['conditions'] : null);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isText($value)
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isList($value)
    {
        return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
    }

    /**
     * Whether a live (not deleted) row of the model has this id.
     *
     * @param  string $model
     * @param  string $id
     * @return bool
     */
    protected function exists($model, $id)
    {
        return (new LiveRecords())->exists($model, $id);
    }
}
