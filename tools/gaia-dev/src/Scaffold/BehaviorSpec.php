<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * What `gaia-dev scaffold behavior` was asked for: the behaviour's name, the model it is registered on and the
 * model events it handles.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BehaviorSpec
{
    /**
     * Model events a Phalcon behaviour is notified of.
     */
    public const EVENTS = [
        'beforeValidation', 'beforeValidationOnCreate', 'beforeValidationOnUpdate', 'afterValidation',
        'afterValidationOnCreate', 'afterValidationOnUpdate', 'beforeSave', 'afterSave', 'beforeCreate',
        'afterCreate', 'beforeUpdate', 'afterUpdate', 'beforeDelete', 'afterDelete', 'afterFetch', 'notSaved',
        'notDeleted', 'onValidationFails',
    ];

    /**
     * @var string e.g. ideaScreensBehavior, the class and the name listed in the model metadata
     */
    public $name;

    /**
     * @var string e.g. IdeaScreensBehaviorTest, the name of the test class
     */
    public $testClass;

    /**
     * @var string the model whose metadata registers the behaviour
     */
    public $model;

    /**
     * @var string[]
     */
    public $events;

    /**
     * @param string $name with or without the Behavior suffix
     * @param array $options parsed command line options: model, events
     * @throws ScaffoldException
     */
    public function __construct(string $name, array $options)
    {
        $name = preg_match('/Behavior$/', $name) ? $name : $name . 'Behavior';
        if (!preg_match('/^[a-z][A-Za-z0-9]*Behavior$/', $name)) {
            throw new ScaffoldException("Behaviour '{$name}' must look like ideaScreensBehavior");
        }
        $this->name = $name;
        $this->testClass = ucfirst($name) . 'Test';

        $model = $options['model'] ?? null;
        if (!is_string($model) || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            throw new ScaffoldException('--model=<Model> is required, e.g. --model=Ideamessage');
        }
        $this->model = $model;

        $events = isset($options['events']) ? array_values(array_filter(explode(',', (string) $options['events']))) : ['beforeSave'];
        foreach ($events as $event) {
            if (!in_array($event, self::EVENTS, true)) {
                throw new ScaffoldException("Unknown event '{$event}'; use one of " . implode(', ', self::EVENTS));
            }
        }
        $this->events = array_values(array_unique($events ?: ['beforeSave']));
    }

    /**
     * @return string repository relative path of the behaviour class
     */
    public function classPath(): string
    {
        return 'core/mvc/models/behaviors/' . $this->name . '.php';
    }

    /**
     * @return string repository relative path of the test
     */
    public function testPath(): string
    {
        return 'tests/models/behaviors/' . $this->testClass . '.php';
    }

    /**
     * @return string repository relative path of the model metadata
     */
    public function metadataPath(): string
    {
        return 'app/metadata/model/' . $this->model . '.php';
    }
}
