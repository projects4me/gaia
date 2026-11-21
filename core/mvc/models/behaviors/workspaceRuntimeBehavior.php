<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkspaceRuntime;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * A workspace may be bound to one runtime: `runtimeId`, when set or changed,
 * must name an active service client of kind runtime (Workspaces D-009). The
 * rule lives in {@see WorkspaceRuntime}.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workspaceRuntimeBehavior extends Behavior implements BehaviorInterface
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
        $runtimeId = $model->readAttribute('runtimeId');
        if (!$rule->isChange($snapshot, $runtimeId)) {
            return true;
        }

        $error = $rule->bindingError($runtimeId);
        if ($error !== null) {
            $model->appendMessage(new Message($error, 'runtimeId', 'InvalidValue'));
            return false;
        }

        return true;
    }

    /**
     * The rule the binding is checked against.
     *
     * @return WorkspaceRuntime
     */
    protected function rule()
    {
        return new WorkspaceRuntime();
    }
}
