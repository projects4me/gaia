<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Tags\TagName;
use Gaia\Libraries\Tags\Tags;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Guards a tag (Tags D-001). On create and update it normalises the name
 * ({@see TagName}) and refuses one that another tag of the project already
 * has, compared case-insensitively. A new tag needs a project, and a tag
 * can't move to another project.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class tagBehavior extends Behavior implements BehaviorInterface
{
    const NEEDS_PROJECT = 'A tag needs a project.';

    const PROJECT_FIXED = 'A tag can\'t be moved to another project.';

    const DUPLICATE = 'The project already has a tag with this name.';

    /**
     * @var Tags|null
     */
    private $tags;

    /**
     * @param array     $options
     * @param Tags|null $tags
     */
    public function __construct(array $options = [], $tags = null)
    {
        parent::__construct($options);
        $this->tags = $tags;
    }

    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        if (!$this->normaliseName($model)) {
            return false;
        }

        $projectId = (string) $model->readAttribute('projectId');
        if ($projectId === '') {
            return $this->refuse($model, self::NEEDS_PROJECT, 'projectId');
        }

        return $this->checkUnique($model, $projectId, null);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        if (!$this->normaliseName($model)) {
            return false;
        }

        $snapshot = $model->getSnapshotData();
        $projectId = (string) $model->readAttribute('projectId');
        if (is_array($snapshot) && array_key_exists('projectId', $snapshot)
            && (string) $snapshot['projectId'] !== $projectId
        ) {
            return $this->refuse($model, self::PROJECT_FIXED, 'projectId');
        }
        if ($projectId === '') {
            // A legacy tag without a project has no project to be unique in.
            return true;
        }

        return $this->checkUnique($model, $projectId, (string) $model->readAttribute('id'));
    }

    /**
     * Stores the normalised name, or refuses one that breaks the text rules.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    private function normaliseName($model)
    {
        $name = TagName::normalise($model->readAttribute('tag'));
        $error = TagName::error($name);
        if ($error !== null) {
            return $this->refuse($model, $error, 'tag');
        }

        $model->writeAttribute('tag', $name);

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @param  string         $projectId
     * @param  string|null    $exceptTagId
     * @return bool
     */
    private function checkUnique($model, $projectId, $exceptTagId)
    {
        if ($this->tags()->nameTaken($projectId, $model->readAttribute('tag'), $exceptTagId)) {
            return $this->refuse($model, self::DUPLICATE, 'tag');
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @param  string         $message
     * @param  string         $field
     * @return bool
     */
    private function refuse($model, $message, $field)
    {
        $model->appendMessage(new Message($message, $field, 'InvalidValue'));

        return false;
    }

    /**
     * @return Tags
     */
    private function tags()
    {
        if ($this->tags === null) {
            $this->tags = new Tags();
        }

        return $this->tags;
    }
}
