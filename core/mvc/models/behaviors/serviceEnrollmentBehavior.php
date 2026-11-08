<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Authorization\ServiceClientScopes;
use Gaia\Libraries\Authorization\ServiceEnrollmentStatus;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks a service enrollment request. A new request has a
 * name, a configured kind of service and the pending status. A status change
 * must follow the enrollment state machine (`ServiceEnrollmentStatus`): an
 * admin approves or denies a pending request before it expires, and the user
 * who decides is stored in `approvedBy`; a request is delivered only with
 * the service client it delivered, which is the only time serviceclientId is
 * written. Everything set when the request is made never changes.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class serviceEnrollmentBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Fields that are set when the request is made and never change.
     *
     * @var array
     */
    public const FIXED_FIELDS = ['name', 'kind', 'userCode', 'pollTokenHash', 'expiresAt', 'requesterIp'];

    /**
     * Statuses that are an admin's decision.
     *
     * @var array
     */
    private const DECISIONS = [ServiceEnrollmentStatus::APPROVED, ServiceEnrollmentStatus::DENIED];

    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        if (trim((string) $model->readAttribute('name')) === '') {
            return $this->refuse($model, 'name is required', 'name');
        }

        $scopes = $this->scopes();
        if ($scopes->defaultsFor((string) $model->readAttribute('kind')) === null) {
            return $this->refuse($model, 'kind must be one of: ' . implode(', ', $scopes->kinds()), 'kind');
        }

        if ((string) $model->readAttribute('status') !== ServiceEnrollmentStatus::PENDING) {
            return $this->refuse($model, 'A new enrollment request is pending.', 'status');
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        $snapshot = is_array($snapshot) ? $snapshot : [];

        foreach (self::FIXED_FIELDS as $field) {
            if (array_key_exists($field, $snapshot)
                && (string) $snapshot[$field] !== (string) $model->readAttribute($field)
            ) {
                return $this->refuse($model, 'The ' . $field . ' of an enrollment request cannot be changed', $field);
            }
        }

        $from = isset($snapshot['status']) ? (string) $snapshot['status'] : '';
        $to = (string) $model->readAttribute('status');

        // approvedBy is written here on a decision; serviceclientId once, on delivery.
        if ($this->changed($snapshot, $model, 'approvedBy') && !($from !== $to && in_array($to, self::DECISIONS, true))) {
            return $this->refuse($model, 'The approver of an enrollment request is set by its decision', 'approvedBy');
        }
        if ($this->changed($snapshot, $model, 'serviceclientId')
            && !($to === ServiceEnrollmentStatus::DELIVERED && $from !== $to
                && (string) ($snapshot['serviceclientId'] ?? '') === '')
        ) {
            return $this->refuse($model, 'The service client of an enrollment request is set by its delivery', 'serviceclientId');
        }

        if ($from === $to) {
            return true;
        }

        $error = ServiceEnrollmentStatus::transitionError($from, $to, $model->readAttribute('expiresAt'), $this->now());
        if ($error !== null) {
            return $this->refuse($model, $error, 'status');
        }

        if ($to === ServiceEnrollmentStatus::DELIVERED && (string) $model->readAttribute('serviceclientId') === '') {
            return $this->refuse($model, 'A request is delivered only with its service client.', 'status');
        }

        if (in_array($to, self::DECISIONS, true)) {
            $model->writeAttribute('approvedBy', $this->currentUserId());
        }

        return true;
    }

    /**
     * The configured kinds of service.
     *
     * @return ServiceClientScopes
     */
    protected function scopes()
    {
        return new ServiceClientScopes();
    }

    /**
     * The current time.
     *
     * @return int Unix time
     */
    protected function now()
    {
        return time();
    }

    /**
     * The id of the user making the change.
     *
     * @return string|null
     */
    protected function currentUserId()
    {
        global $currentUser;

        return isset($currentUser->id) ? (string) $currentUser->id : null;
    }

    /**
     * Whether a stored field has a new value.
     *
     * @param  array          $snapshot
     * @param  ModelInterface $model
     * @param  string         $field
     * @return bool
     */
    private function changed(array $snapshot, $model, $field)
    {
        return array_key_exists($field, $snapshot)
            && (string) $snapshot[$field] !== (string) $model->readAttribute($field);
    }

    /**
     * @param  ModelInterface $model
     * @param  string         $message
     * @param  string         $field
     * @return bool
     */
    private function refuse($model, $message, $field)
    {
        $model->appendMessage(new Message($message, $field));
        return false;
    }
}
