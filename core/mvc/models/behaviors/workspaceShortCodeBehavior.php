<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Exception\Conflict;
use Gaia\Libraries\Workspaces\WorkspaceShortCode;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Checks a workspace's short code when it is set or changed (Workspaces
 * D-012): a missing or malformed code is a message on shortCode (422), a code
 * another live workspace uses throws (409). The rule lives in
 * {@see WorkspaceShortCode}. A save that leaves the code as stored passes, so
 * rows from before the column keep saving until they get a code.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workspaceShortCodeBehavior extends Behavior implements BehaviorInterface
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
     * @throws Conflict
     */
    protected function check($model, $snapshot)
    {
        $rule = $this->rule();
        $raw = $model->readAttribute('shortCode');
        $code = $rule->normalize($raw);
        if (!$rule->isChange($snapshot, $code)) {
            return true;
        }

        $error = $rule->formatError($code);
        if ($error !== null) {
            $model->appendMessage(new Message($error, 'shortCode', 'InvalidValue'));
            return false;
        }
        if ($rule->isTaken($code, $model->readAttribute('id'))) {
            throw new Conflict(WorkspaceShortCode::TAKEN);
        }

        $model->shortCode = $code;
        return true;
    }

    /**
     * The rule the code is checked against.
     *
     * @return WorkspaceShortCode
     */
    protected function rule()
    {
        return new WorkspaceShortCode();
    }
}
