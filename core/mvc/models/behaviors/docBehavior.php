<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Di;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior guards the lock of a doc (Docs D-005): only the doc's stored
 * owner, or a user who may update projects (`project.update`), may lock or
 * unlock it. Other changes to the doc are left to the ACL.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class docBehavior extends Behavior implements BehaviorInterface
{
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
     * The owner is read from the stored row, so a request cannot make itself
     * the owner and change the lock in one save. The owner itself is guarded
     * the same way, so an editor cannot take the doc over and change the lock
     * in the next save.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (!is_array($snapshot)) {
            return true;
        }

        $field = $this->guardedFieldChanged($model, $snapshot);
        if ($field === null) {
            return true;
        }

        $userId = $this->currentUserId();
        if ($userId !== null && $userId === (string) ($snapshot['ownerId'] ?? '')) {
            return true;
        }
        if ($this->mayUpdateProjects()) {
            return true;
        }

        $model->appendMessage(new Message(
            $field === 'locked'
                ? 'Only the owner of the doc or a project admin may lock or unlock it.'
                : 'Only the owner of the doc or a project admin may change its owner.',
            $field,
            'InvalidValue'
        ));
        return false;
    }

    /**
     * The first guarded field (`locked`, then `ownerId`) this save changes.
     *
     * @param  ModelInterface $model
     * @param  array          $snapshot
     * @return string|null
     */
    private function guardedFieldChanged($model, array $snapshot)
    {
        if (array_key_exists('locked', $snapshot)
            && self::isLocked($snapshot['locked']) !== self::isLocked($model->locked)
        ) {
            return 'locked';
        }
        if (array_key_exists('ownerId', $snapshot)
            && (string) $snapshot['ownerId'] !== (string) $model->ownerId
        ) {
            return 'ownerId';
        }

        return null;
    }

    /**
     * Whether a stored or sent locked value means locked.
     *
     * @param  mixed $value
     * @return bool
     */
    public static function isLocked($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'on', 'yes'], true);
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
     * Whether the request's ACL allows `project.update`. Outside a request
     * (no ACL loaded) only the owner may change the lock.
     *
     * @return bool
     */
    protected function mayUpdateProjects()
    {
        $di = Di::getDefault();
        if ($di === null || !$di->has('acl')) {
            return false;
        }

        return $di->get('acl')->isModelActionAllowed('Project', 'update');
    }
}
