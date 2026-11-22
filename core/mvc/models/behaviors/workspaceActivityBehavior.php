<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkspaceActivity;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Records a workspace's own activities (Workspaces D-018): created, and the
 * name, short code, description or runtime binding updated. The update reads
 * the changes `auditBehavior` collected before the save. The descriptions
 * live in {@see WorkspaceActivity}.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class workspaceActivityBehavior extends Behavior implements BehaviorInterface
{
    /**
     * @param string         $eventType
     * @param ModelInterface $model
     * @return void
     */
    public function notify($eventType, ModelInterface $model)
    {
        if ($eventType === 'afterCreate') {
            $this->activity()->created($model);
        }
        if ($eventType === 'afterUpdate') {
            $this->activity()->updated($model, $model->audit ?? null);
        }
    }

    /**
     * @return WorkspaceActivity
     */
    protected function activity()
    {
        return new WorkspaceActivity();
    }
}
