<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * What `gaia-dev scaffold live-events` was asked for: the model, the events it publishes, the event name prefix,
 * the fields each event carries and the parent key the project id is resolved through.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveEventsSpec
{
    /**
     * Event => the component hook that publishes it.
     */
    public const HOOKS = ['created' => 'afterCreate', 'updated' => 'afterUpdate', 'deleted' => 'afterDelete'];

    public const EVENTS_DIRECTORY = 'app/api/v1/controllers/components/events';

    public const TEST_PATH = 'tests/api/controllers/components/events/LiveEventsComponentsTest.php';

    /**
     * @var string e.g. Ideamessage
     */
    public $model;

    /**
     * @var string[] created, updated and/or deleted
     */
    public $events;

    /**
     * @var string e.g. idea.message, the dotted event name prefix; defaults to the lower case model name
     */
    public $prefix;

    /**
     * @var string[] fields carried by the created event and watched for the updated event
     */
    public $fields;

    /**
     * @var string[]|null [foreign key, parent model] to add to ProjectIdResolver, null when the model has projectId
     */
    public $parent;

    /**
     * @param string $model
     * @param array $options parsed command line options: events, prefix, fields, parent
     * @throws ScaffoldException
     */
    public function __construct(string $model, array $options)
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            throw new ScaffoldException("Model '{$model}' must look like Ideamessage");
        }
        $this->model = $model;

        $events = isset($options['events']) ? array_values(array_filter(explode(',', (string) $options['events']))) : ['created'];
        foreach ($events as $event) {
            if (!isset(self::HOOKS[$event])) {
                throw new ScaffoldException("Unknown event '{$event}'; use created, updated or deleted");
            }
        }
        $this->events = array_values(array_unique($events ?: ['created']));

        $prefix = isset($options['prefix']) ? (string) $options['prefix'] : strtolower($model);
        if (!preg_match('/^[a-z]+(\.[a-z]+)*$/', $prefix)) {
            throw new ScaffoldException("--prefix '{$prefix}' must look like idea.message");
        }
        $this->prefix = $prefix;

        $fields = isset($options['fields']) ? array_values(array_filter(explode(',', (string) $options['fields']))) : [];
        foreach ($fields as $field) {
            if (!preg_match('/^[a-z][A-Za-z0-9]*$/', $field)) {
                throw new ScaffoldException("Field '{$field}' must look like title or dateStart");
            }
        }
        $this->fields = array_values(array_unique($fields));

        if (isset($options['parent'])) {
            if (!preg_match('/^([a-z][A-Za-z0-9]*):([A-Z][A-Za-z0-9]*)$/', (string) $options['parent'], $parent)) {
                throw new ScaffoldException('--parent must look like ideaId:Idea');
            }
            $this->parent = [$parent[1], $parent[2]];
        }
    }

    /**
     * @param string $event created, updated or deleted
     * @return string e.g. idea.message.created
     */
    public function eventName(string $event): string
    {
        return $this->prefix . '.' . $event;
    }

    /**
     * @param string $event created, updated or deleted
     * @return string e.g. IDEA_MESSAGE_CREATED
     */
    public function constant(string $event): string
    {
        return strtoupper(str_replace('.', '_', $this->eventName($event)));
    }

    /**
     * @return string e.g. IdeamessageLiveEventsComponent
     */
    public function componentClass(): string
    {
        return $this->model . 'LiveEventsComponent';
    }

    /**
     * @return string e.g. Events\IdeamessageLiveEvents, the entry in a controller's $uses
     */
    public function usesName(): string
    {
        return 'Events\\' . $this->model . 'LiveEvents';
    }

    /**
     * @return string repository relative path of the component
     */
    public function componentPath(): string
    {
        return self::EVENTS_DIRECTORY . '/' . $this->componentClass() . '.php';
    }

    /**
     * @return string repository relative path of the model's controller
     */
    public function controllerPath(): string
    {
        return 'app/api/v1/controllers/' . $this->model . 'Controller.php';
    }
}
