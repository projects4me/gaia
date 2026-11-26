<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Tags\Tags;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Guards a tagging (Tags D-001). On create the record must be a live
 * taggable one ({@see Tags::TAGGABLE}), the tag must belong to the record's
 * project, and the record must not have the tag yet. A tagging can't be
 * changed afterwards: delete it and create a new one.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class taggedBehavior extends Behavior implements BehaviorInterface
{
    const NOT_TAGGABLE = 'Only a live issue, conversation room, wiki page or doc can be tagged.';

    const UNKNOWN_TAG = 'The tag doesn\'t exist or belongs to no project.';

    const OTHER_PROJECT = 'The tag belongs to another project than the record.';

    const DUPLICATE = 'The record already has this tag.';

    const IMMUTABLE = 'A tagging can\'t be changed: delete it and create a new one.';

    const IMMUTABLE_FIELDS = ['tagId', 'relatedTo', 'relatedId'];

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
        $tagId = (string) $model->readAttribute('tagId');
        $relatedTo = (string) $model->readAttribute('relatedTo');
        $relatedId = (string) $model->readAttribute('relatedId');
        if ($tagId === '' || $relatedTo === '' || $relatedId === '') {
            // Required-field validation reports the missing value.
            return true;
        }

        $recordProjectId = $this->tags()->projectOfRecord($relatedTo, $relatedId);
        if ($recordProjectId === null) {
            return $this->refuse($model, self::NOT_TAGGABLE, 'relatedId');
        }

        $tagProjectId = $this->tags()->projectOfTag($tagId);
        if ($tagProjectId === null) {
            return $this->refuse($model, self::UNKNOWN_TAG, 'tagId');
        }
        if ($tagProjectId !== $recordProjectId) {
            return $this->refuse($model, self::OTHER_PROJECT, 'tagId');
        }

        if ($this->tags()->isApplied($tagId, $relatedTo, $relatedId)) {
            return $this->refuse($model, self::DUPLICATE, 'tagId');
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (!is_array($snapshot) || $snapshot === []) {
            return true;
        }

        foreach (self::IMMUTABLE_FIELDS as $field) {
            if (array_key_exists($field, $snapshot)
                && (string) $snapshot[$field] !== (string) $model->readAttribute($field)
            ) {
                return $this->refuse($model, self::IMMUTABLE, $field);
            }
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
