<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;
use Phalcon\Messages\Message;


/**
 * This behavior handles the soft deletion of a record
 * reference https://forum.phalconphp.com/discussion/4494/i-made-an-improved-softdelete
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 */
class softDeleteBehavior extends Behavior implements BehaviorInterface
{
    /**
     *  This function is called when ever an event is fired
     *
     * @param string                      $eventType
     * @param \Phalcon\Mvc\ModelInterface $model
     */
    public function notify($eventType,ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            $this->$eventType($model);
        }
        return $model;
    }

    /**
     * This function is called before a model is deleted
     *
     * @param $model
     * @return bool
     */
    protected function beforeDelete(&$model)
    {
        $field = 'deleted';
        $value = '1';

        $model->skipOperation(true);

        if ($model->readAttribute($field) === $value) {
            $model->appendMessage(new Message('Model was already deleted'));
            return false;
        }

        if (method_exists($model, 'beforeSoftDelete')) {
            $model->beforeSoftDelete();
        }

        $updateModel = clone $model;
        $updateModel->writeAttribute($field, $value);
        $updateModel->isDeleting = true;
        if (!$updateModel->update()) {
            foreach ($updateModel->getMessages() as $message) {
                $model->appendMessage($message);
            }
            return false;
        }

        $model->writeAttribute($field, $value);

        if (method_exists($model, 'afterSoftDelete')) {
            $model->afterSoftDelete();
        }
    }

    /**
     * This function makes sure that we are setting the deleted bit to 0
     * on creation and update
     *
     * @param $model
     */
    protected function beforeValidation(&$model)
    {
        if (!(isset($model->isDeleting) && $model->isDeleting)) {
            $model->writeAttribute('deleted', 0);
        }
    }

    /**
     * This function appends deleted clause in the query being executed
     *
     * @param $model
     */
    protected function beforeQuery(&$model)
    {
        $GLOBALS['logger']->debug("Setting deleted = 0 for ".$model->modelAlias);
        $model->getQuery()->getPhalconQueryBuilder()->AndWhere($model->modelAlias.".deleted = '0'");
    }
}