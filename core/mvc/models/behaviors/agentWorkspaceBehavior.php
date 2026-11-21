<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\AgentWorkspace;
use Gaia\MVC\Models\Workspace;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * An agent belongs to one existing workspace, given when it is created and
 * never changed (Workspaces D-006). The rule lives in {@see AgentWorkspace}.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class agentWorkspaceBehavior extends Behavior implements BehaviorInterface
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
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        $workspaceId = (string) $model->readAttribute('workspaceId');
        if ($workspaceId === '') {
            return $this->reject($model, AgentWorkspace::WORKSPACE_REQUIRED);
        }

        $exists = Workspace::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => $workspaceId],
        ]);
        if (!$exists) {
            return $this->reject($model, 'workspaceId must be the id of an existing workspace');
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        if ((new AgentWorkspace())->isChange($model->getSnapshotData(), $model->readAttribute('workspaceId'))) {
            return $this->reject($model, AgentWorkspace::WORKSPACE_FIXED);
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @param  string         $message
     * @return bool
     */
    protected function reject($model, $message)
    {
        $model->appendMessage(new Message($message, 'workspaceId', 'InvalidValue'));
        return false;
    }
}
