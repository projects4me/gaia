<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Evaluates Filter and Switch conditions against a run's values. A
 * condition is `{field, operator, value}`: `field` is a dotted path into
 * the values, `value` may hold an expression, and all conditions of a list
 * must hold (see NodeConfigRules::OPERATORS).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ConditionEvaluator
{
    /**
     * @var ExpressionResolver
     */
    private $expressions;

    /**
     * @param ExpressionResolver $expressions
     */
    public function __construct(ExpressionResolver $expressions)
    {
        $this->expressions = $expressions;
    }

    /**
     * The port a Filter takes: `pass` when every condition holds, else `fail`.
     *
     * @param  array $config
     * @param  array $values
     * @return string
     */
    public function filter(array $config, array $values)
    {
        $conditions = isset($config['conditions']) && is_array($config['conditions']) ? $config['conditions'] : [];

        return $this->all($conditions, $values) ? 'pass' : 'fail';
    }

    /**
     * The port a Switch takes: the key of the first case whose conditions
     * all hold, else the default port.
     *
     * @param  array $config
     * @param  array $values
     * @return string
     */
    public function switchPort(array $config, array $values)
    {
        $cases = isset($config['cases']) && is_array($config['cases']) ? $config['cases'] : [];
        foreach ($cases as $case) {
            $conditions = isset($case['conditions']) && is_array($case['conditions']) ? $case['conditions'] : [];
            if ($conditions !== [] && $this->all($conditions, $values)) {
                return (string) $case['key'];
            }
        }

        return NodeKindCatalog::DEFAULT_PORT;
    }

    /**
     * Whether every condition holds.
     *
     * @param  array $conditions
     * @param  array $values
     * @return bool
     */
    public function all(array $conditions, array $values)
    {
        foreach ($conditions as $condition) {
            if (!is_array($condition) || !$this->holds($condition, $values)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array $condition
     * @param  array $values
     * @return bool
     */
    private function holds(array $condition, array $values)
    {
        $actual = ExpressionResolver::path($values, isset($condition['field']) ? $condition['field'] : '');
        $expected = $this->expressions->resolve(isset($condition['value']) ? $condition['value'] : null, $values);
        $operator = isset($condition['operator']) ? $condition['operator'] : null;

        switch ($operator) {
            case 'equals':
                return self::same($actual, $expected);
            case 'notEquals':
                return !self::same($actual, $expected);
            case 'contains':
                return is_array($actual)
                    ? self::inList($expected, $actual)
                    : $expected !== null && strpos((string) $actual, (string) $expected) !== false;
            case 'in':
                return self::inList($actual, self::listOf($expected));
            case 'notIn':
                return !self::inList($actual, self::listOf($expected));
            case 'empty':
                return self::isEmpty($actual);
            case 'notEmpty':
                return !self::isEmpty($actual);
            case 'gt':
                return !self::isEmpty($actual) && self::compare($actual, $expected) > 0;
            case 'lt':
                return !self::isEmpty($actual) && self::compare($actual, $expected) < 0;
        }

        return false;
    }

    /**
     * Values compare as text, so `5` and `"5"` are the same.
     *
     * @param  mixed $a
     * @param  mixed $b
     * @return bool
     */
    private static function same($a, $b)
    {
        if (self::isEmpty($a) || self::isEmpty($b)) {
            return self::isEmpty($a) && self::isEmpty($b);
        }

        return is_scalar($a) && is_scalar($b) && (string) $a === (string) $b;
    }

    /**
     * @param  mixed $value
     * @param  array $list
     * @return bool
     */
    private static function inList($value, array $list)
    {
        foreach ($list as $item) {
            if (self::same($value, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A list, or a comma-separated text, as a list.
     *
     * @param  mixed $value
     * @return array
     */
    private static function listOf($value)
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (self::isEmpty($value)) {
            return [];
        }

        return array_map('trim', explode(',', (string) $value));
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private static function isEmpty($value)
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Numbers compare as numbers, anything else as text.
     *
     * @param  mixed $a
     * @param  mixed $b
     * @return int
     */
    private static function compare($a, $b)
    {
        if (is_numeric($a) && is_numeric($b)) {
            return $a + 0 <=> $b + 0;
        }

        return strcmp(self::scalarText($a), self::scalarText($b));
    }

    /**
     * @param  mixed $value
     * @return string
     */
    private static function scalarText($value)
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
