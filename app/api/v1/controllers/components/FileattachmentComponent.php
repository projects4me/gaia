<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use \Phalcon\Events\Event as Event;
use function Gaia\Libraries\Utils\create_guid as create_guid;
use \Gaia\MVC\Models\Activity;
use Phalcon\Text;
/**
 * Class FileattachmentComponent
 *
 * @class FileattachmentComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class FileattachmentComponent
{

    /**
     * This function handles the creation of activity upon attachment of a file
     *
     * @param Event $event
     * @param $controller
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Fileattachment::afterCreate()');

        if (isset($model->id)){
            $activity = new Activity();
            $activity->id = create_guid();
            $activity->description = 'File <b>'.$model->name.'</b> attached';
            $activity->relatedTo = $model->relatedTo;
            $activity->relatedId = $model->relatedId;
            $activity->relatedActivity = 'attached';
            $activity->relatedActivityId = $model->id;
            $activity->relatedActivityModule = 'file';
            $activity->type = 'related';
            $activity->save();
        }

        $logger->debug('-Gaia.Controller.Component.Fileattachment::afterCreate()');
    }


    /**
     * This function handles the creation of activity upon attachment of a file
     *
     * @param Event $event
     * @param $controller
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Fileattachment::afterDelete()');

        if (isset($model->id)){
            $activity = new Activity();
            $activity->id = create_guid();
            $activity->description = 'File <b>'.$model->name.'</b> deleted';
            $activity->relatedTo = $model->relatedTo;
            $activity->relatedId = $model->relatedId;
            $activity->relatedActivity = 'deleted';
            $activity->relatedActivityId = $model->id;
            $activity->relatedActivityModule = 'file';
            $activity->type = 'related';
            $activity->save();
        }

        $logger->debug('-Gaia.Controller.Component.Fileattachment::afterDelete()');
    }

}