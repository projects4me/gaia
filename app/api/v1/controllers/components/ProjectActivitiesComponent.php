<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event as Event;
use Gaia\MVC\Models\Activity;
use Phalcon\Text;

use function Gaia\Libraries\Utils\create_guid as create_guid;

/**
 * Log the activities of a project and its related entities.
 *
 * @class ProjectActivitiesComponent
 * @author  Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components
 */
class ProjectActivitiesComponent
{
    /**
     * Meta data for project activities
     *
     * @var array
     * @access protected
     */
    protected $activityMeta = [
        'project' => [
            'type' => 'model',
            'afterUpdate' => [
                'status' => [
                    'message' => "Status changed from <b>%s</b> to <b>%s</b>"
                ],
                'endDate' => [
                    'messageHandler' => "recordProjectEndDateActivity"
                ],
                'assignee' => [
                    'messageHandler' => 'recordOwnerActivity'
                ]
            ],
            'afterCreate' => [
                'messageHandler' => 'projectCreatedActivity'
            ]
        ],
        'milestone' => [
            'type' => 'rel',
            'relatedKey' => 'projectId',
            'afterUpdate' => [
                'status' => [
                    'message' => "Milestone status changed from <b>%s</b> to <b>%s</b>"
                ],
                'name' => [
                    'message' => "Milestone name changed from <b>%s</b> to <b>%s</b>"
                ]
            ],
            'afterCreate' => [
                'messageHandler' => 'milestoneCreatedActivity'
            ]
        ],
        'membership' => [
            'type' => 'rel',
            'relatedKey' => 'relatedId',
            'afterCreate' => [
                'messageHandler' => 'membershipCreatedActivity'
            ]
        ]
    ];

    /**
     * This function handles insertion of activities on project and its relationships update.
     *
     * @param Event $event
     * @param $controller
     * @param $model
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.ProjectActivities::afterUpdate()');
        $controllerName = $controller->getControllerName();

        $fields = $this->activityMeta[$controllerName]['afterUpdate'];
        $activityConfig = $this->activityMeta[$controllerName];

        foreach ($fields as $field => $config) {
            if (isset($model->audit[$field]) && $model->audit[$field]['old'] !== $model->audit[$field]['new']) {
                $activityDescription = $this->getActivityDescription($model, $activityConfig['afterUpdate'][$field], $field);
                $this->logActivity($model, $activityConfig, $controllerName, $activityDescription);
            }
        }

        $logger->debug('-Gaia.Controller.Component.ProjectActivities::afterUpdate()');
    }

    /**
     * This function handles the creation of activity upon creation of project or its relationships.
     *
     * @param Event $event
     * @param $controller
     * @param $model
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.ProjectActivities::afterCreate()');
        $logger->debug($model->id);
        $controllerName = $controller->getControllerName();

        $fields = $this->activityMeta[$controllerName]['afterCreate'];
        $activityConfig = $this->activityMeta[$controllerName];

        if (isset($model->id)) {
            $activityDescription = $this->getActivityDescription($model, $activityConfig['afterCreate']);
            $this->logActivity($model, $activityConfig, $controllerName, $activityDescription, 'created');
        }

        $logger->debug('-Gaia.Controller.Component.ProjectActivities::afterCreate()');
    }

    /**
     * Logs an activity related to a project or its related entities.
     *
     * @param object $model The model instance that has been updated.
     * @param array $activityConfig Configuration array for the activity, specifying type and related keys.
     * @param string $controllerName The name of the controller handling the activity.
     * @param string $activityDescription A description of the activity being logged.
     * @param string $activityType The type of activity being logged (default is 'updated').
     *
     * @throws \Phalcon\Exception If there is an error while saving the activity.
     */
    protected function logActivity($model, $activityConfig, $controllerName, $activityDescription, $activityType = 'updated')
    {
        try {
            $activity = new Activity();
            $activity->id = create_guid();
            $activity->description = $activityDescription;
            $activity->relatedTo = 'project';

            if ($activityConfig['type'] === 'model') {
                $activity->relatedId = $model->id;
                $activity->type = $activityType;
            } elseif ($activityConfig['type'] === 'rel') {
                $activity->relatedActivityModule = $controllerName;
                $activity->relatedId = $model->{$activityConfig['relatedKey']};
                $activity->relatedActivity =  $activityType;
                $activity->type = 'related';
            }
            $activity->save();
        } catch (\Exception $e) {
            throw new \Phalcon\Exception($e->getMessage());
        }
    }

    /**
     * Get the activity description based on the provided model, activity configuration, and field.
     *
     * @param mixed $model The model object.
     * @param array $activityConfig The activity configuration array.
     * @param string|null $field The field name (optional).
     * @return string The activity description.
     */
    protected function getActivityDescription($model, $activityConfig, $field = null)
    {
        $messageTemplate = isset($activityConfig['message']) ? $activityConfig['message'] : null;
        $messageHandler = isset($activityConfig['messageHandler']) ? $activityConfig['messageHandler'] : null;
        $message = '';

        if ($messageTemplate && $field) {
            $oldValue = ucwords(Text::humanize($model->audit[$field]['old']));
            $newValue = ucwords(Text::humanize($model->audit[$field]['new']));
            $message = sprintf($messageTemplate, $oldValue, $newValue);
        } elseif ($messageHandler &&  method_exists($this, $messageHandler)) {
            $message = $this->{$messageHandler}($model);
        }

        return $message;
    }

    /**
     * Record the owner activity based on the provided model.
     *
     * @param \Gaia\MVC\Models\User $model The model object.
     * @return string The owner activity description.
     */
    protected function recordOwnerActivity($model)
    {
        $exOwner = (\Gaia\MVC\Models\User::findFirstById($model->audit['assignee']['old']));
        $newOwner = \Gaia\MVC\Models\User::findFirstById($model->audit['assignee']['new']);
        return "Owner changed from <b>$exOwner->name</b> to <b>$newOwner->name</b>";
    }

    /**
     * Generate the activity description for a created project.
     *
     * @param \Gaia\MVC\Models\Project $model The model object.
     * @return string The project created activity description.
     */
    protected function projectCreatedActivity($model)
    {
        return "Project <b>$model->name</b> created";
    }

    /**
     * Generate the activity description for a created milestone.
     *
     * @param \Gaia\MVC\Models\Milestone $model The model object.
     * @return string The milestone created activity description.
     */
    protected function milestoneCreatedActivity($model)
    {
        return "Milestone <b>$model->name</b> added";
    }

    /**
     * Generate the activity description for a created membership.
     *
     * @param \Gaia\MVC\Models\Membership $model The model object.
     * @return string The membership created activity description.
     */
    protected function membershipCreatedActivity($model)
    {
        $user = \Gaia\MVC\Models\User::findFirstById($model->userId);
        $role = \Gaia\MVC\Models\Role::findFirstById($model->roleId);

        return "New member <b>$user->name</b> has been added to the project as <b>$role->name</b>";
    }

    /**
     * Generate the activity description for a project end date update.
     *
     * @param \Gaia\MVC\Models\Project $model The model object.
     * @return string The project end date activity description.
     */
    protected function recordProjectEndDateActivity($model) {
        $oldEndDate = $model->audit['endDate']['old'];
        $newEnddate = $model->audit['endDate']['new'];

        return "End date for the project was rescheduled from <b>$oldEndDate</b> to <b>$newEnddate</b>";
    }
}
