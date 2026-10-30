<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\Libraries\Workflows\LoopGuard;
use Gaia\Libraries\Workflows\TriggerDispatcher;
use Gaia\Libraries\Workflows\TriggerEventCatalog;
use Gaia\Libraries\Workflows\TriggerPayload;
use Gaia\Libraries\Workflows\WorkflowChangeEvents;
use Gaia\Libraries\Workflows\WorkflowRunner;
use Gaia\MVC\REST\Controllers\Components\Events\Support\LiveEventEnvelope;
use Gaia\MVC\REST\Controllers\Components\Events\Support\ProjectIdResolver;
use Phalcon\Events\Event;

/**
 * Raises the workflow trigger events of a successful create or update (see
 * TriggerEventCatalog) and hands each to the workflows it starts. Like live
 * events this fails open: a workflow problem never fails the user's write.
 * It also raises the events of the writes a workflow run makes, which
 * carry the run so the run cannot trigger itself.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components\Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowTriggersComponent implements WorkflowChangeEvents
{
    /**
     * @var TriggerEventCatalog
     */
    protected $catalog;

    /**
     * @var TriggerPayload
     */
    protected $payloads;

    /**
     * @var TriggerDispatcher|null Built on first use, so a write that raises
     *                             no trigger event never touches it.
     */
    protected $dispatcher;

    /**
     * @var ProjectIdResolver
     */
    protected $resolver;

    /**
     * @param TriggerEventCatalog|null $catalog
     * @param TriggerPayload|null      $payloads
     * @param TriggerDispatcher|null   $dispatcher
     * @param ProjectIdResolver|null   $resolver
     */
    public function __construct(
        TriggerEventCatalog $catalog = null,
        TriggerPayload $payloads = null,
        TriggerDispatcher $dispatcher = null,
        ProjectIdResolver $resolver = null
    ) {
        $this->catalog = $catalog ?: new TriggerEventCatalog();
        $this->payloads = $payloads ?: new TriggerPayload();
        $this->dispatcher = $dispatcher;
        $this->resolver = $resolver ?: new ProjectIdResolver();
    }

    /**
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model The persisted record.
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->raise($model, TriggerEventCatalog::ON_CREATE, []);
    }

    /**
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model The persisted record, with its audit diff.
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $audit = isset($model->audit) && is_array($model->audit) ? $model->audit : [];
        if ($audit) {
            $this->raise($model, TriggerEventCatalog::ON_UPDATE, $audit);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function changed($record, $on, array $run)
    {
        $audit = isset($record->audit) && is_array($record->audit) ? $record->audit : [];
        if ($on === TriggerEventCatalog::ON_CREATE || $audit) {
            $this->raise($record, $on, $on === TriggerEventCatalog::ON_CREATE ? [] : $audit, $run);
        }
    }

    /**
     * Hands each event the write raised to the dispatcher.
     *
     * @param  mixed  $model
     * @param  string $on
     * @param  array  $audit field => ['old' => ..., 'new' => ...]
     * @param  array  $run   The workflow run that made the write, if one did (see LoopGuard).
     * @return void
     */
    protected function raise($model, $on, array $audit, array $run = [])
    {
        global $logger;

        try {
            $id = $this->recordId($model);
            $modelName = is_object($model) ? (new \ReflectionClass($model))->getShortName() : null;
            if ($id === null || $modelName === null) {
                return;
            }
            $names = $this->catalog->raisedBy($modelName, $on, array_keys($audit));
            if (!$names) {
                return;
            }

            $projectId = $this->resolver->resolve($model);
            $record = $this->record($model);
            foreach ($names as $name) {
                $envelope = LiveEventEnvelope::build($name, $projectId, TriggerPayload::recordKey($modelName), $id);
                $payload = $this->payloads->forRecord($envelope, $modelName, $record, $audit);
                if ($run) {
                    $payload[LoopGuard::KEY] = $run;
                }
                $this->dispatcher()->dispatch($payload);
            }
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Workflow trigger failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @return TriggerDispatcher
     */
    protected function dispatcher()
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new TriggerDispatcher(null, new WorkflowRunner($this));
        }

        return $this->dispatcher;
    }

    /**
     * @param  mixed $model
     * @return string|null
     */
    protected function recordId($model)
    {
        foreach (['id', 'newId'] as $field) {
            if (isset($model->$field) && $model->$field !== '') {
                return (string) $model->$field;
            }
        }

        return null;
    }

    /**
     * The record's values after the write.
     *
     * @param  mixed $model
     * @return array
     */
    protected function record($model)
    {
        $values = method_exists($model, 'toArray') ? $model->toArray() : get_object_vars($model);
        unset($values['audit']);

        return is_array($values) ? $values : [];
    }
}
