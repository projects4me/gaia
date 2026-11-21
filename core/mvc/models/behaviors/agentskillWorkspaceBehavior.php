<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\CatalogWorkspace;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * A skill grant stays inside the agent's workspace: the skill must be in it (Workspaces D-007). The rule lives in {@see CatalogWorkspace}.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class agentskillWorkspaceBehavior extends Behavior implements BehaviorInterface
{
    /**
     * @var CatalogWorkspace
     */
    protected $rule;

    /**
     * @param CatalogWorkspace|null $rule
     */
    public function __construct($rule = null)
    {
        $this->rule = $rule ?: new CatalogWorkspace();
    }

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
        if (!$this->rule->mayGrantSkill($model->readAttribute('agentId'), $model->readAttribute('skillId'))) {
            $model->appendMessage(new Message(CatalogWorkspace::SKILL_OUTSIDE, 'skillId', 'InvalidValue'));
            return false;
        }

        return true;
    }

    /**
     * Editing a grant must not move it outside the workspace either.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        return $this->beforeValidationOnCreate($model);
    }
}
