<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;
use Gaia\MVC\REST\Controllers\Components\Events\Support\ProjectIdResolver;
use Phalcon\Events\Event;

/**
 * This component is the shared publisher for selective Hermes domain events.
 * Subclasses own their create/update/delete rules. This base only resolves a
 * project id and posts a best-effort envelope.
 *
 * @class   LiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
abstract class LiveEventsComponent
{
    /**
     * HTTP publisher used to POST the envelope to Hermes.
     *
     * @var  HermesPublisher
     * @type HermesPublisher
     */
    protected $publisher;

    /**
     * Resolves a project id when the model does not carry one.
     *
     * @var  ProjectIdResolver
     * @type ProjectIdResolver
     */
    protected $resolver;

    /**
     * Creates the component with optional test doubles.
     *
     * @param  HermesPublisher|null    $publisher Publisher used to POST envelopes.
     * @param  ProjectIdResolver|null  $resolver  Resolver used when projectId is missing.
     * @method __construct
     * @return void
     */
    public function __construct($publisher = null, $resolver = null)
    {
        $this->publisher = $publisher ?: new HermesPublisher();
        $this->resolver = $resolver ?: new ProjectIdResolver();
    }

    /**
     * Hook after a successful create. Subclasses override to publish.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted model.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
    }

    /**
     * Hook after a successful update. Subclasses override to publish.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted model.
     * @method afterUpdate
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
    }

    /**
     * Hook after a successful delete. Subclasses override to publish.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The deleted model.
     * @method afterDelete
     * @return void
     */
    public function afterDelete(Event $event, $controller, $model)
    {
    }

    /**
     * Resolves a project id and posts a domain-event envelope. Fail-open.
     *
     * @param  string $eventName    Allowlisted event name.
     * @param  mixed  $model        The persisted model.
     * @param  string $resourceType Resource type string.
     * @param  string $resourceId   Persisted resource id.
     * @param  array  $changes      Field values the client can apply.
     * @param  array  $meta         Extra envelope metadata.
     * @param  mixed  $projectId    Optional project id; resolved from the model when empty.
     * @method publish
     * @return void
     */
    protected function publish(
        $eventName,
        $model,
        $resourceType,
        $resourceId,
        array $changes = array(),
        array $meta = array(),
        $projectId = null
    ) {
        global $logger;

        try {
            if (empty($projectId) && isset($model->projectId)) {
                $projectId = $model->projectId;
            }
            if (empty($projectId)) {
                $projectId = $this->resolver->resolve($model);
            }
            if (empty($projectId) || empty($eventName) || empty($resourceId)) {
                return;
            }

            $this->publisher->publishDomainEvent(
                $eventName,
                $projectId,
                $resourceType,
                $resourceId,
                $changes,
                $meta
            );
            if ($logger) {
                $logger->info('Live event published: ' . $eventName . ' project=' . $projectId);
            }
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Live event publish failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Returns the persisted id, preferring id then newId.
     *
     * @param  mixed $model The persisted model.
     * @method modelId
     * @return string|null
     */
    protected function modelId($model)
    {
        if (isset($model->id) && $model->id !== '') {
            return (string) $model->id;
        }
        if (isset($model->newId) && $model->newId !== '') {
            return (string) $model->newId;
        }
        return null;
    }

    /**
     * Returns true when the value is null, empty string, or false.
     *
     * @param  mixed $value The value to test.
     * @method isBlank
     * @return bool
     */
    protected function isBlank($value)
    {
        return $value === null || $value === '' || $value === false;
    }

    /**
     * Copies the named fields from an array or object into a new array.
     *
     * @param  array $fields Field names to copy.
     * @param  mixed $source Array or model to read from.
     * @method pick
     * @return array
     */
    protected function pick(array $fields, $source)
    {
        $out = array();
        foreach ($fields as $field) {
            if (is_array($source) && array_key_exists($field, $source)) {
                $out[$field] = $source[$field];
            } elseif (is_object($source) && isset($source->$field)) {
                $out[$field] = $source->$field;
            }
        }
        return $out;
    }

    /**
     * Returns the old or new audit value for a field.
     *
     * @param  mixed  $model The persisted model with an audit array.
     * @param  string $field Field name in $model->audit.
     * @param  string $which Which side of the diff (old or new).
     * @method auditValue
     * @return mixed
     */
    protected function auditValue($model, $field, $which = 'new')
    {
        if (empty($model->audit) || !is_array($model->audit) || !isset($model->audit[$field])) {
            return null;
        }
        $diff = $model->audit[$field];
        if (is_array($diff) && array_key_exists($which, $diff)) {
            return $diff[$which];
        }
        return null;
    }

    /**
     * Builds a changes map from the new audit values of the given fields.
     *
     * @param  mixed $model  The persisted model with an audit array.
     * @param  array $fields Field names to include when present in audit.
     * @method changesFromAudit
     * @return array
     */
    protected function changesFromAudit($model, array $fields)
    {
        $changes = array();
        foreach ($fields as $field) {
            if (!empty($model->audit) && is_array($model->audit) && array_key_exists($field, $model->audit)) {
                $changes[$field] = $this->auditValue($model, $field, 'new');
            }
        }
        return $changes;
    }

    /**
     * Returns true when the model is related to a conversation room.
     * Comments and votes on issues, wiki, etc. are not live-synced.
     *
     * @param  mixed $model The comment or vote model.
     * @method isConversationRelated
     * @return bool
     */
    protected function isConversationRelated($model)
    {
        $relatedTo = isset($model->relatedTo) ? strtolower((string) $model->relatedTo) : '';
        return in_array($relatedTo, array('conversationroom', 'conversationrooms'), true);
    }
}
