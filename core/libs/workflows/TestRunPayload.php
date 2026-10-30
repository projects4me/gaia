<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\Libraries\Security\Acl;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Builds the trigger payload a test run starts from: the payload the trigger's
 * event would carry for a chosen record, so conditions and expressions see
 * what a real run sees. Nothing is raised or saved. The record's fields the
 * user may not read are left out, so neither the run nor its response shows them.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TestRunPayload
{
    /**
     * @var RecordEventEnvelopes
     */
    private $envelopes;

    /**
     * @var Acl|null
     */
    private $acl;

    /**
     * @var TriggerEventCatalog
     */
    private $events;

    /**
     * @var TriggerPayload
     */
    private $payloads;

    /**
     * @var WorkflowRecords
     */
    private $records;

    /**
     * @param RecordEventEnvelopes     $envelopes
     * @param Acl|null                 $acl       The user's field grants; every field when null.
     * @param TriggerEventCatalog|null $events
     * @param TriggerPayload|null      $payloads
     * @param WorkflowRecords|null     $records
     */
    public function __construct(
        RecordEventEnvelopes $envelopes,
        Acl $acl = null,
        TriggerEventCatalog $events = null,
        TriggerPayload $payloads = null,
        WorkflowRecords $records = null
    ) {
        $this->envelopes = $envelopes;
        $this->acl = $acl;
        $this->events = $events ?: new TriggerEventCatalog();
        $this->payloads = $payloads ?: new TriggerPayload();
        $this->records = $records ?: new WorkflowRecords();
    }

    /**
     * The payload of the trigger's event for a record, or of a schedule firing.
     *
     * @param  array         $trigger    The trigger node: kind and config.
     * @param  string|null   $entityType The model the record is of; checked against the event's.
     * @param  string|null   $entityId   The record the event is about (not for a schedule).
     * @param  array         $changes    Field => {before, after}, for update events.
     * @param  string|null   $projectId  For a schedule, the project it is about.
     * @return array
     * @throws TestRunRefusal
     */
    public function build(
        array $trigger,
        $entityType,
        $entityId,
        array $changes,
        $projectId = null
    ) {
        $kind = $trigger['kind'];
        if ($kind === TriggerEventCatalog::SCHEDULE) {
            $cron = isset($trigger['config']['cron']) ? $trigger['config']['cron'] : '';

            return $this->payloads->forSchedule(create_guid(), $cron, new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $projectId);
        }
        if (!$this->events->has($kind)) {
            throw new TestRunRefusal("The trigger {$kind} cannot be test run.", 422);
        }

        $model = $this->events->definition($kind)['model'];
        if ($entityType !== null && $entityType !== '' && strcasecmp((string) $entityType, $model) !== 0) {
            throw new TestRunRefusal("The trigger {$kind} is about a {$model}, not a {$entityType}.", 422);
        }
        if ($entityId === null || $entityId === '') {
            throw new TestRunRefusal("entityId is required: the trigger {$kind} is about a {$model}.", 422);
        }
        $record = $this->records->find($model, $entityId);
        if ($record === null) {
            throw new TestRunRefusal("{$model} {$entityId} does not exist.", 404);
        }

        $audit = [];
        foreach ($changes as $field => $change) {
            $change = is_array($change) ? $change : [];
            $audit[$field] = [
                'old' => array_key_exists('before', $change) ? $change['before'] : null,
                'new' => array_key_exists('after', $change) ? $change['after'] : null,
            ];
        }
        $values = $this->readable($model, $record->toArray());
        unset($values['audit']);

        return $this->payloads->forRecord(
            $this->envelopes->envelope($kind, $record, TriggerPayload::recordKey($model), $entityId),
            $model,
            $values,
            $audit
        );
    }

    /**
     * The record's values the user may read.
     *
     * @param  string $model
     * @param  array  $values
     * @return array
     */
    private function readable($model, array $values)
    {
        if ($this->acl === null) {
            return $values;
        }

        return array_filter($values, function ($field) use ($model) {
            return $this->acl->isFieldActionAllowed($model, $field, 'get');
        }, ARRAY_FILTER_USE_KEY);
    }
}
