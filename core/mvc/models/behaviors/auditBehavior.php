<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;
use Gaia\Libraries\Utils\Util;

/**
 * This behavior serves the purpose of maintaining the Audit Information.
 * Which really is if an field has during an update
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 */
class auditBehavior extends Behavior implements BehaviorInterface
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
     * This function is called before a model is to be changed
     *
     * @param ModelInterface $model
     * @return ModelInterface
     */
    protected function beforeUpdate(&$model)
    {
        // System fields are managed by the system and never audited (D-071).
        $changedFields = array_diff($model->getChangedFields(), $this->getSystemFields($model));
        if (count($changedFields) > 0) {
            $originalData = $model->getSnapshotData();
            $model->isChanged = true;
            $audit = array();
            foreach ($changedFields as $field) {
                $audit[$field] = array(
                    'old' => $originalData[$field],
                    'new' => $model->$field
                );
            }
            $model->audit = $audit;
        }
    }

    /**
     * The model's fields flagged `system` in its metadata.
     *
     * @param ModelInterface $model
     * @return array
     */
    protected function getSystemFields($model)
    {
        $modelName = Util::extractClassFromNamespace(get_class($model));
        return $model->getDI()->get('metaManager')->getSystemFields($modelName);
    }
}
