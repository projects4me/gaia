<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\MVC\Models\Idea;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior numbers the plan versions of an idea: a new plan gets the
 * idea's highest version + 1 (1, 2, 3, ...), and the version never changes
 * afterwards. Deleted plans keep their number, so it is never reused.
 * The idea row is locked while numbering, so plans created for one idea at
 * the same moment take turns instead of colliding on (ideaId, version); the
 * model saves in a transaction that holds the lock until commit.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class planVersionBehavior extends Behavior implements BehaviorInterface
{
    /**
     * This function is called whenever an event is triggered
     * from a model e.g. beforeCreate or afterUpdate
     *
     * @param string $eventType
     * @param ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            $this->$eventType($model);
        }
    }

    /**
     * Assign the next version of the idea, whatever the request sent.
     *
     * @param ModelInterface $model
     * @return void
     */
    protected function beforeValidationOnCreate(&$model)
    {
        $this->lockIdea($model);
        $model->version = $this->nextVersion($this->getLatestVersion($model));
    }

    /**
     * Keep the version assigned on create; a plan moved to another idea
     * takes that idea's next version.
     *
     * @param ModelInterface $model
     * @return void
     */
    protected function beforeValidationOnUpdate(&$model)
    {
        $snapshot = $model->getSnapshotData();
        if (isset($snapshot['ideaId']) && (string) $snapshot['ideaId'] !== (string) $model->ideaId) {
            $this->lockIdea($model);
            $model->version = $this->nextVersion($this->getLatestVersion($model));
            return;
        }
        if (isset($snapshot['version'])) {
            $model->version = $snapshot['version'];
        }
    }

    /**
     * The version that follows the idea's latest one.
     *
     * @param int|string|null $latestVersion
     * @return int
     */
    public function nextVersion($latestVersion)
    {
        return ((int) $latestVersion) + 1;
    }

    /**
     * Lock the plan's idea row (SELECT ... FOR UPDATE) until the save's
     * transaction ends, so only one plan of the idea is numbered at a time.
     * Outside a transaction the lock would end with the SELECT, so the model
     * must save in one (see Ideaplan::save).
     *
     * @param ModelInterface $model
     * @return void
     * @throws \LogicException When the save is not in a transaction
     */
    protected function lockIdea($model)
    {
        if (!$model->getWriteConnection()->isUnderTransaction()) {
            throw new \LogicException(get_class($model) . ' must save in a transaction to number plan versions');
        }

        Idea::findFirst([
            'conditions' => 'id = :ideaId:',
            'bind' => ['ideaId' => (string) $model->ideaId],
            'for_update' => true,
        ]);
    }

    /**
     * The highest version among the idea's plans, deleted ones included.
     *
     * @param ModelInterface $model
     * @return int|string|null Null when the idea has no plan yet
     */
    protected function getLatestVersion($model)
    {
        $modelClass = get_class($model);

        return $modelClass::maximum([
            'column' => 'version',
            'conditions' => 'ideaId = :ideaId:',
            'bind' => ['ideaId' => (string) $model->ideaId],
        ]);
    }
}
