<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\ProjectWorkspace;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Gives every new project a workspace and refuses moving a project to another
 * one (Workspaces D-002). The rule itself lives in {@see ProjectWorkspace}.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class projectWorkspaceBehavior extends Behavior implements BehaviorInterface
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
     * Runs after the model's own validation, so no workspace is created for a
     * project that fails it.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeCreate($model)
    {
        try {
            $model->workspaceId = (new ProjectWorkspace())
                ->resolveForNewProject($model->workspaceId ?? null, $model->name ?? '');
        } catch (\InvalidArgumentException $e) {
            $model->appendMessage(new Message($e->getMessage(), 'workspaceId', 'InvalidValue'));
            return false;
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $workspace = new ProjectWorkspace();
        if (!$workspace->isMove($model->getSnapshotData(), $model->workspaceId ?? null)) {
            return true;
        }

        $model->appendMessage(new Message(ProjectWorkspace::MOVE_UNSUPPORTED, 'workspaceId', 'InvalidValue'));
        return false;
    }
}
