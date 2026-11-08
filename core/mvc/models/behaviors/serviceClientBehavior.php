<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\Libraries\Authorization\ServiceClientScopes;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks a service client: `kind` is a configured kind of
 * service, `status` is active or disabled (active when not given), and
 * `scopes` is a JSON list of scope names (a list sent as JSON is stored as
 * its JSON text). A client created without scopes gets its kind's default
 * scopes (`ServiceClientScopes::defaultsFor`). `kind` and `clientId` never
 * change once the client exists, and disabling a client deletes its access
 * tokens.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class serviceClientBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Fields that are set when the client is created and never change.
     *
     * @var array
     */
    public const FIXED_FIELDS = ['kind', 'clientId'];

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
        $scopes = $this->scopes();
        $kind = (string) $model->readAttribute('kind');
        $defaults = $scopes->defaultsFor($kind);
        if ($defaults === null) {
            $model->appendMessage(new Message(
                'kind must be one of: ' . implode(', ', $scopes->kinds()),
                'kind'
            ));
            return false;
        }

        if ((string) $model->readAttribute('status') === '') {
            $model->writeAttribute('status', ServiceClients::STATUS_ACTIVE);
        }

        $stored = $model->readAttribute('scopes');
        if ($stored === null || $stored === '' || $stored === []) {
            $model->writeAttribute('scopes', json_encode($defaults));
        }

        return $this->checkClient($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        foreach (self::FIXED_FIELDS as $field) {
            if (is_array($snapshot) && array_key_exists($field, $snapshot)
                && (string) $snapshot[$field] !== (string) $model->readAttribute($field)
            ) {
                $model->appendMessage(new Message('The ' . $field . ' of a service client cannot be changed', $field));
                return false;
            }
        }

        return $this->checkClient($model);
    }

    /**
     * Disabling a client deletes its access tokens, so it is cut off at once.
     *
     * Runs once the update has passed validation and before it is written, so
     * a client is never stored as disabled while its tokens still work.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        $wasDisabled = is_array($snapshot) && isset($snapshot['status'])
            && (string) $snapshot['status'] === ServiceClients::STATUS_DISABLED;

        if (!$wasDisabled && (string) $model->readAttribute('status') === ServiceClients::STATUS_DISABLED) {
            $this->serviceClients()->revokeTokens((string) $model->readAttribute('clientId'));
        }

        return true;
    }

    /**
     * The service that revokes a client's tokens.
     *
     * @return ServiceClients
     */
    protected function serviceClients()
    {
        return new ServiceClients();
    }

    /**
     * The configured kinds of service and their default scopes.
     *
     * @return ServiceClientScopes
     */
    protected function scopes()
    {
        return new ServiceClientScopes();
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkClient($model)
    {
        if (!in_array((string) $model->readAttribute('status'), ServiceClients::STATUSES, true)) {
            $model->appendMessage(new Message(
                'status must be one of: ' . implode(', ', ServiceClients::STATUSES),
                'status'
            ));
            return false;
        }

        $scopes = $model->readAttribute('scopes');
        if (is_array($scopes)) {
            $scopes = json_encode($scopes);
            $model->writeAttribute('scopes', $scopes);
        }

        if (ServiceClientScopes::decode($scopes) === null) {
            $model->appendMessage(new Message('scopes must be a JSON list of scope names', 'scopes'));
            return false;
        }

        return true;
    }
}
