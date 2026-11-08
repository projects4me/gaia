<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Authorization\ServiceClientScopes;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks a service enrollment token. A new token has a name and
 * a configured kind of service. After that only `usedAt` may change, once,
 * when the token is spent (revoking is deleting, which the soft delete
 * behavior handles).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class serviceEnrollmentTokenBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Fields that are set when the token is made and never change.
     *
     * @var array
     */
    public const FIXED_FIELDS = ['name', 'kind', 'tokenHash', 'expiresAt'];

    /**
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

        if ((string) $model->readAttribute('usedAt') !== '') {
            return $this->refuse($model, 'A new enrollment token is unused.', 'usedAt');
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
                return $this->refuse($model, 'The ' . $field . ' of an enrollment token cannot be changed', $field);
            }
        }

        if (array_key_exists('usedAt', $snapshot)
            && (string) $snapshot['usedAt'] !== ''
            && (string) $snapshot['usedAt'] !== (string) $model->readAttribute('usedAt')
        ) {
            return $this->refuse($model, 'An enrollment token is used only once', 'usedAt');
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
