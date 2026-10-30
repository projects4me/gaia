<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The node kinds a workflow can use: the category each kind belongs to, the
 * config fields it takes and the output ports it offers. A port is where an
 * edge leaves the node: a switch has one per case plus `default`, a run of
 * an agent one per outcome.
 *
 * Config field types:
 * - `string`: a non-empty string;
 * - `cron`: a five-field cron expression (see CronExpression);
 * - `ref:<Model>`: the id of a live row of that model (`User`, `Agent`), or
 *   an expression over the event such as `{issue.assigneeId}`;
 * - `conditions`: a non-empty list of `{field, operator, value}` objects;
 * - `cases`: a non-empty list of `{key, conditions}` objects with unique keys.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NodeKindCatalog
{
    public const TRIGGER = 'trigger';
    public const CONDITION = 'condition';
    public const ACTOR = 'actor';
    public const ACTION = 'action';

    /**
     * The port a switch takes when no case matches.
     */
    public const DEFAULT_PORT = 'default';

    /**
     * The kind that ends a path explicitly.
     */
    public const END = 'end';

    /**
     * Kinds by category but triggers: each kind lists its config fields
     * (name => [type, required]) and its fixed output ports. `cases` ports
     * come from config. Trigger kinds come from TriggerEventCatalog.
     *
     * @var array
     */
    private const KINDS = [
        self::CONDITION => [
            'filter' => ['config' => ['conditions' => ['conditions', true]], 'ports' => ['pass', 'fail']],
            'switch' => ['config' => ['cases' => ['cases', true]], 'ports' => [self::DEFAULT_PORT]],
        ],
        self::ACTOR => [
            'run_agent' => [
                'config' => ['agentId' => ['ref:Agent', true], 'prompt' => ['string', false]],
                'ports' => ['done', 'needs_input', 'blocked', 'failed', 'limit'],
            ],
            'ask_user' => [
                'config' => ['userId' => ['ref:User', true], 'question' => ['string', true]],
                'ports' => ['replied'],
            ],
        ],
        self::ACTION => [
            'assign' => ['config' => ['assigneeId' => ['ref:User', true]], 'ports' => ['next']],
            'set_field' => ['config' => ['field' => ['string', true], 'value' => ['string', true]], 'ports' => ['next']],
            'set_status' => ['config' => ['status' => ['string', true]], 'ports' => ['next']],
            'add_comment' => [
                'config' => ['body' => ['string', true], 'ownerId' => ['ref:User', false]],
                'ports' => ['next'],
            ],
            'create_issue' => [
                'config' => [
                    'subject' => ['string', true],
                    'description' => ['string', false],
                    'typeId' => ['ref:Issuetype', false],
                    'priority' => ['string', false],
                    'ownerId' => ['ref:User', false],
                    'assigneeId' => ['ref:User', false],
                ],
                'ports' => ['next'],
            ],
            'create_wiki' => [
                'config' => ['title' => ['string', true], 'body' => ['string', false], 'ownerId' => ['ref:User', false]],
                'ports' => ['next'],
            ],
            'notify' => [
                'config' => ['userId' => ['ref:User', true], 'message' => ['string', true]],
                'ports' => ['next'],
            ],
            'call_tool' => ['config' => ['toolId' => ['string', true]], 'ports' => ['next']],
            self::END => ['config' => [], 'ports' => []],
        ],
    ];

    /**
     * @return string[]
     */
    public function categories()
    {
        return array_keys(self::kinds());
    }

    /**
     * Whether the kind belongs to the category.
     *
     * @param  string $category
     * @param  string $kind
     * @return bool
     */
    public function has($category, $kind)
    {
        return isset(self::kinds()[$category][$kind]);
    }

    /**
     * The kind's config fields: name => ['type' => string, 'required' => bool].
     *
     * @param  string $category
     * @param  string $kind
     * @return array
     */
    public function configFields($category, $kind)
    {
        $fields = [];
        foreach ($this->spec($category, $kind)['config'] as $name => list($type, $required)) {
            $fields[$name] = ['type' => $type, 'required' => $required];
        }

        return $fields;
    }

    /**
     * The output ports of a node of this kind with this config.
     *
     * @param  string $category
     * @param  string $kind
     * @param  array  $config
     * @return string[]
     */
    public function ports($category, $kind, array $config)
    {
        $ports = [];
        foreach ($this->configFields($category, $kind) as $name => $field) {
            if ($field['type'] === 'cases' && isset($config[$name]) && is_array($config[$name])) {
                foreach ($config[$name] as $case) {
                    if (is_array($case) && isset($case['key']) && is_string($case['key'])) {
                        $ports[] = $case['key'];
                    }
                }
            }
        }

        return array_values(array_unique(array_merge($ports, $this->spec($category, $kind)['ports'])));
    }

    /**
     * The users and agents a node of this kind with this config names, as
     * ['entityType' => <Model>, 'entityId' => <id>] pairs. Expressions name
     * no one until the workflow runs, so they are left out.
     *
     * @param  string $category
     * @param  string $kind
     * @param  array  $config
     * @return array
     */
    public function references($category, $kind, array $config)
    {
        $references = [];
        foreach ($this->configFields($category, $kind) as $name => $field) {
            $entityType = self::refModel($field['type']);
            if ($entityType === null || !isset($config[$name]) || !is_string($config[$name])) {
                continue;
            }
            $value = trim($config[$name]);
            if ($value !== '' && !self::isExpression($value)) {
                $references[] = ['entityType' => $entityType, 'entityId' => $value];
            }
        }

        return $references;
    }

    /**
     * The model a `ref:<Model>` type points at, or null for other types.
     *
     * @param  string $type
     * @return string|null
     */
    public static function refModel($type)
    {
        return strpos($type, 'ref:') === 0 ? substr($type, 4) : null;
    }

    /**
     * Whether a value is an expression over the event, e.g. `{issue.assigneeId}`.
     *
     * @param  string $value
     * @return bool
     */
    public static function isExpression($value)
    {
        return (bool) preg_match('/^\{[^{}]+\}$/', trim($value));
    }

    /**
     * @param  string $category
     * @param  string $kind
     * @return array
     */
    private function spec($category, $kind)
    {
        if (!$this->has($category, $kind)) {
            throw new \InvalidArgumentException("Unknown workflow node kind {$category}/{$kind}.");
        }

        return self::kinds()[$category][$kind];
    }

    /**
     * Every kind by category: the trigger events and the schedule first.
     *
     * @return array
     */
    private static function kinds()
    {
        static $kinds = null;
        if ($kinds === null) {
            $triggers = [];
            foreach ((new TriggerEventCatalog())->names() as $name) {
                $triggers[$name] = ['config' => [], 'ports' => ['next']];
            }
            $triggers[TriggerEventCatalog::SCHEDULE] = ['config' => ['cron' => ['cron', true]], 'ports' => ['next']];
            $kinds = [self::TRIGGER => $triggers] + self::KINDS;
        }

        return $kinds;
    }
}
