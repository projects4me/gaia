<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;


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

        if (method_exists($this, $eventType))
        {
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
        $changedFields = $model->getChangedFields();
        if ( count($changedFields) > 0){
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

}