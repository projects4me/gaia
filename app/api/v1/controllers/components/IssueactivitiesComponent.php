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
 * Class IssueactivitiesComponent
 *
 * @class IssueactivitiesComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class IssueactivitiesComponent
{
    /**
     * This function handles insertion of activities on issue update
     *
     * @param Event $event
     * @param $controller
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        global $logger, $currentUser;
        $logger->debug('Gaia.Controller.Component.Issueactivities::afterUpdate()');

        if ($model->isChanged) {

            if (isset($model->audit['status'])){
                $oldStatus = ucwords(Text::humanize($model->audit['status']['old']));
                $newStatus = ucwords(Text::humanize($model->audit['status']['new']));
                $activity = new Activity();
                $activity->id = create_guid();
                $activity->description = "Status changed from '".$oldStatus."' to '".$newStatus."'";
                $activity->createdUser = $currentUser->id;
                $activity->relatedTo = 'issue';
                $activity->relatedId = $model->id;
                $activity->type = 'updated';
                $activity->createdUserName = $currentUser->name;
                $activity->save();
            }
        }

        $logger->debug('-Gaia.Controller.Component.Issueactivities::afterUpdate()');
    }

    /**
     * This function handles the creation of activity upon creation of an issue
     *
     * @param Event $event
     * @param $controller
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger, $currentUser;
        $logger->debug('Gaia.Controller.Component.Issueactivities::afterCreate()');

        $logger->debug($model->id);
        if (isset($model->id)){
            $activity = new Activity();
            $activity->id = create_guid();
            $activity->description = 'Issue created';
            $activity->createdUser = $currentUser->id;
            $activity->relatedTo = 'issue';
            $activity->relatedId = $model->id;
            $activity->type = 'created';
            $activity->createdUserName = $currentUser->name;
            $activity->save();
        }

        $logger->debug('-Gaia.Controller.Component.Issueactivities::afterCreate()');
    }

}