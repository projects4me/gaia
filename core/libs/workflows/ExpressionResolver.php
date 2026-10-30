<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Resolves the expressions in a node's settings against a run's values
 * (the trigger payload, see TriggerPayload).
 *
 * - `{issue.number}` names a value by its dotted path; a setting that is
 *   exactly one expression keeps the value's type.
 * - `Fix {issue.subject}` is a template: each expression is replaced by its
 *   text, and a missing value by nothing.
 * - `{name:argument}` calls a function the caller registers, such as
 *   `{role:Developer}` or `{sponsor:issue.assignee}` (see UserSelector).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ExpressionResolver
{
    /**
     * @var callable[] name => function (string $argument, array $values)
     */
    private $functions;

    /**
     * @param callable[] $functions name => function (string $argument, array $values)
     */
    public function __construct(array $functions = [])
    {
        $this->functions = $functions;
    }

    /**
     * The value of a setting: an expression's value, a filled-in template,
     * or the setting itself when it holds no expression.
     *
     * @param  mixed $setting
     * @param  array $values
     * @return mixed
     * @throws WorkflowStepException When a function is unknown.
     */
    public function resolve($setting, array $values)
    {
        if (!is_string($setting)) {
            return $setting;
        }
        if (NodeKindCatalog::isExpression($setting)) {
            return $this->evaluate(trim(trim($setting), '{}'), $values);
        }

        // Only brace text shaped like a path or a function call is an
        // expression; other braces (such as code) stay as they are.
        return preg_replace_callback('/\{\s*([A-Za-z_][\w.]*(?:\s*:[^{}]*)?)\s*\}/', function ($match) use ($values) {
            return self::text($this->evaluate($match[1], $values));
        }, $setting);
    }

    /**
     * The value at a dotted path, or null when the path is missing.
     *
     * @param  array  $values
     * @param  string $path
     * @return mixed
     */
    public static function path(array $values, $path)
    {
        $value = $values;
        foreach (explode('.', trim((string) $path)) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param  string $expression
     * @param  array  $values
     * @return mixed
     */
    private function evaluate($expression, array $values)
    {
        $expression = trim($expression);
        $colon = strpos($expression, ':');
        if ($colon === false) {
            return self::path($values, $expression);
        }

        $name = trim(substr($expression, 0, $colon));
        if (!isset($this->functions[$name])) {
            throw new WorkflowStepException("{{$expression}} uses an unknown function '{$name}'.");
        }

        return call_user_func($this->functions[$name], trim(substr($expression, $colon + 1)), $values);
    }

    /**
     * @param  mixed $value
     * @return string
     */
    private static function text($value)
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
