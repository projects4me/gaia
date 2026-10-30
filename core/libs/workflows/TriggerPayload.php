<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Builds the payload a workflow starts with. A domain event's payload is its
 * live-event envelope (eventId, eventName, occurredAt, projectId, resource,
 * actorId) plus:
 * - the record after the change, under the model's key (`issue`, `comment`,
 *   `wiki`, `timelog`), limited to the model's fields;
 * - `changes`: each changed field with its `before` and `after` value;
 * - `actor`: the id and name of the user who made the change.
 *
 * A schedule's payload has no project or record, only `schedule`: the cron
 * expression and the minute it fired for.
 *
 * Expressions and filters address the payload by path, e.g. `issue.subject`
 * or `changes.status.after` (see TriggerEventDescriber).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TriggerPayload
{
    /**
     * Paths every domain-event payload has, with their editor types.
     *
     * @var array
     */
    public const COMMON_FIELDS = [
        'projectId' => 'string',
        'occurredAt' => 'datetime',
        'actor.id' => 'string',
        'actor.name' => 'string',
    ];

    /**
     * Paths of a schedule payload, with their editor types.
     *
     * @var array
     */
    public const SCHEDULE_FIELDS = [
        'occurredAt' => 'datetime',
        'schedule.cron' => 'string',
        'schedule.firedAt' => 'datetime',
    ];

    /**
     * @var ModelFieldTypes
     */
    private $fieldTypes;

    /**
     * @param ModelFieldTypes|null $fieldTypes
     */
    public function __construct(ModelFieldTypes $fieldTypes = null)
    {
        $this->fieldTypes = $fieldTypes ?: new ModelFieldTypes();
    }

    /**
     * The key a model's record sits under in the payload, e.g. `issue`.
     *
     * @param  string $model
     * @return string
     */
    public static function recordKey($model)
    {
        return strtolower((string) $model);
    }

    /**
     * The payload of a domain event on a record.
     *
     * @param  array  $envelope The event's live-event envelope.
     * @param  string $model    Model name, e.g. `Issue`.
     * @param  array  $record   The record's values after the change.
     * @param  array  $audit    The change: field => ['old' => ..., 'new' => ...].
     * @return array
     */
    public function forRecord(array $envelope, $model, array $record, array $audit)
    {
        $payload = $envelope;
        $payload['actor'] = [
            'id' => isset($envelope['actorId']) ? $envelope['actorId'] : null,
            'name' => isset($envelope['meta']['actorName']) ? $envelope['meta']['actorName'] : null,
        ];
        $payload[self::recordKey($model)] = array_intersect_key($record, $this->fieldTypes->forModel($model));
        $payload['changes'] = self::changes($audit);

        return $payload;
    }

    /**
     * The payload of a schedule trigger firing.
     *
     * @param  string             $eventId
     * @param  string             $cron
     * @param  \DateTimeInterface $firedAt The minute the schedule fired for.
     * @return array
     */
    public function forSchedule($eventId, $cron, \DateTimeInterface $firedAt)
    {
        return [
            'eventId' => (string) $eventId,
            'eventName' => TriggerEventCatalog::SCHEDULE,
            'occurredAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'projectId' => null,
            'schedule' => [
                'cron' => (string) $cron,
                'firedAt' => $firedAt->format('Y-m-d\TH:i:s\Z'),
            ],
        ];
    }

    /**
     * Each changed field with its value before and after, bookkeeping left out.
     *
     * @param  array $audit field => ['old' => ..., 'new' => ...]
     * @return array
     */
    public static function changes(array $audit)
    {
        $changes = [];
        foreach ($audit as $field => $diff) {
            if (!is_array($diff) || in_array($field, TriggerEventCatalog::BOOKKEEPING_FIELDS, true)) {
                continue;
            }
            $changes[$field] = [
                'before' => array_key_exists('old', $diff) ? $diff['old'] : null,
                'after' => array_key_exists('new', $diff) ? $diff['new'] : null,
            ];
        }

        return $changes;
    }
}
