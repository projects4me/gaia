<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\Models\Behaviors\docBehavior;
use Gaia\MVC\REST\Controllers\Components\Events\Support\CollabNotifier;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes doc create, update and delete events to Hermes,
 * scoped to the doc's project, and tells the collab server when a doc is
 * locked, unlocked or deleted.
 *
 * @class   DocLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class DocLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Doc fields included on doc.created and whose change publishes doc.updated.
     *
     * @var  array
     * @type array
     */
    const TRACKED_FIELDS = array('title', 'locked', 'ownerId');

    /**
     * Notifier for the collab server.
     *
     * @var  CollabNotifier
     * @type CollabNotifier
     */
    protected $collab;

    /**
     * @param  mixed               $publisher Publisher used to POST envelopes.
     * @param  mixed               $resolver  Resolver used when projectId is missing.
     * @param  CollabNotifier|null $collab    Collab server notifier.
     * @method __construct
     * @return void
     */
    public function __construct($publisher = null, $resolver = null, $collab = null)
    {
        parent::__construct($publisher, $resolver);
        $this->collab = $collab ?: new CollabNotifier();
    }

    /**
     * Publishes doc.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted doc.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $fields = $this->pick(self::TRACKED_FIELDS, $model);
        if (array_key_exists('locked', $fields)) {
            $fields['locked'] = docBehavior::isLocked($fields['locked']);
        }

        $this->publishDocEvent(EventNames::DOC_CREATED, $model, $fields);
    }

    /**
     * Publishes doc.updated for changed tracked fields and notifies the
     * collab server when locked changed.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted doc.
     * @method afterUpdate
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $changes = $this->changesFromAudit($model, self::TRACKED_FIELDS);

        if (array_key_exists('locked', $changes)) {
            $wasLocked = docBehavior::isLocked($this->auditValue($model, 'locked', 'old'));
            $changes['locked'] = docBehavior::isLocked($changes['locked']);
            if ($wasLocked === $changes['locked']) {
                unset($changes['locked']);
            }
        }

        if (empty($changes)) {
            return;
        }

        $this->publishDocEvent(EventNames::DOC_UPDATED, $model, $changes);

        if (array_key_exists('locked', $changes)) {
            $id = $this->modelId($model);
            if ($id !== null) {
                $this->collab->notifyAccessChanged(
                    $id,
                    $changes['locked'] ? CollabNotifier::REASON_LOCKED : CollabNotifier::REASON_UNLOCKED
                );
            }
        }
    }

    /**
     * Publishes doc.deleted and notifies the collab server.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The deleted doc.
     * @method afterDelete
     * @return void
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        $this->publishDocEvent(EventNames::DOC_DELETED, $model, array());

        $id = $this->modelId($model);
        if ($id !== null) {
            $this->collab->notifyAccessChanged($id, CollabNotifier::REASON_DELETED);
        }
    }

    /**
     * Publishes a doc event with the doc id in meta.
     *
     * @param  string $eventName Allowlisted doc event name.
     * @param  mixed  $model     The doc model.
     * @param  array  $changes   Field values the client can apply.
     * @method publishDocEvent
     * @return void
     */
    protected function publishDocEvent($eventName, $model, array $changes)
    {
        $id = $this->modelId($model);
        if ($id === null) {
            return;
        }

        $this->publish($eventName, $model, 'doc', $id, $changes, array('docId' => $id));
    }
}
