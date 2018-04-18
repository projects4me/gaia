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
 * Class TimeloggedComponent
 *
 * @class TimeloggedComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class TimeloggedComponent
{

    /**
     * This function handles the creation of activity upon time logged against
     * an issue
     *
     * @param Event $event
     * @param $controller
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Timelogged::afterCreate()');

        if (isset($model->id)){
            $activity = new Activity();
            $activity->id = create_guid();
            $activity->description = '<b>'.$this->getTimeText($model).'</b> logged on '.date('M jS `y', strtotime($model->spentOn));
            $activity->relatedTo = 'issue';
            $activity->relatedId = $model->issueId;
            $activity->relatedActivity = 'created';
            $activity->relatedActivityId = $model->id;
            $activity->relatedActivityModule = 'timelog';
            $activity->type = 'related';
            $activity->save();
        }

        $logger->debug('-Gaia.Controller.Component.Timelogged::afterCreate()');
    }


    /**
     * This function handles the creation of activity upon update in time log
     *
     * @param Event $event
     * @param $controller
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.Timelogged::afterUpdate()');

        if (isset($model->id)){
            $activity = new Activity();
            $activity->id = create_guid();
            $activity->description = 'Time logged on '.date('M jS `y', strtotime($model->spentOn)).' updated to <b>'.$this->getTimeText($model).'</b>';
            $activity->relatedTo = 'issue';
            $activity->relatedId = $model->issueId;
            $activity->relatedActivity = 'updated';
            $activity->relatedActivityId = $model->id;
            $activity->relatedActivityModule = 'timelog';
            $activity->type = 'related';
            $activity->save();
        }

        $logger->debug('-Gaia.Controller.Component.Timelogged::afterUpdate()');
    }

    private function getTimeText($model)
    {
        $timeText = array();
        if ($model->minutes > 0) {
            $timeText[] = $model->minutes.'m';
        }
        if ($model->hours > 0) {
            $timeText[] = $model->hours.'h';
        }
        if ($model->days > 0) {
            $timeText[] = $model->days.'d';
        }
        return implode(' ',$timeText);
    }

}