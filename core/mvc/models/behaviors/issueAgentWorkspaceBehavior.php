<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\AgentWorkspace;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * An issue can be assigned to an agent only when its project is in the
 * agent's workspace, on create and when the assignee or project changes
 * (Workspaces D-006). The rule lives in {@see AgentWorkspace}.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class issueAgentWorkspaceBehavior extends Behavior implements BehaviorInterface
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
        return $this->check($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (is_array($snapshot)
            && (string) ($snapshot['assignee'] ?? '') === (string) $model->readAttribute('assignee')
            && (string) ($snapshot['projectId'] ?? '') === (string) $model->readAttribute('projectId')
        ) {
            return true;
        }

        return $this->check($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function check($model)
    {
        $allowed = (new AgentWorkspace())->mayWorkIn(
            $model->readAttribute('assignee'),
            $model->readAttribute('projectId')
        );
        if ($allowed) {
            return true;
        }

        $model->appendMessage(new Message(AgentWorkspace::ASSIGNEE_OUTSIDE, 'assignee', 'InvalidValue'));
        return false;
    }
}
