<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkspaceManager;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * A workspace has a manager (Workspaces D-022): `managerId` defaults to the
 * creator, and when set or changed it must name a live user. The rule lives
 * in {@see WorkspaceManager}. Runs after createdUserBehavior, which sets the
 * creator.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workspaceManagerBehavior extends Behavior implements BehaviorInterface
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
        if ($eventType === 'beforeValidationOnCreate') {
            return $this->check($model, null);
        }
        if ($eventType === 'beforeValidationOnUpdate') {
            return $this->check($model, $model->getSnapshotData());
        }
    }

    /**
     * @param  ModelInterface $model
     * @param  array|null     $snapshot
     * @return bool
     */
    protected function check($model, $snapshot)
    {
        $rule = $this->rule();
        $managerId = $model->readAttribute('managerId');
        if (is_array($snapshot) && !$rule->isChange($snapshot, $managerId)) {
            return true;
        }

        $managerId = $rule->managerFor($managerId, $model->readAttribute('createdUser'));
        $error = $rule->managerError($managerId);
        if ($error !== null) {
            $model->appendMessage(new Message($error, 'managerId', 'InvalidValue'));
            return false;
        }

        $model->managerId = $managerId;
        return true;
    }

    /**
     * The rule the manager is checked against.
     *
     * @return WorkspaceManager
     */
    protected function rule()
    {
        return new WorkspaceManager();
    }
}
