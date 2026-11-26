<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

/**
 * A fake Tag or Tagged model for the tag behaviour tests, keeping the message
 * fields and texts.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagFakeModel extends \Phalcon\Mvc\Model
{
    public $id;

    public $tag;

    public $projectId;

    public $tagId;

    public $relatedTo;

    public $relatedId;

    /**
     * @var string[]
     */
    public $messages = [];

    /**
     * @var string[]
     */
    public $texts = [];

    /**
     * @var array
     */
    private $fakeSnapshot = [];

    /**
     * A model without the ORM's constructor (no DI or metadata needed).
     *
     * @param  array $attributes
     * @param  array $snapshot
     * @return self
     */
    public static function make(array $attributes, array $snapshot = [])
    {
        $model = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        foreach ($attributes as $name => $value) {
            $model->$name = $value;
        }
        $model->fakeSnapshot = $snapshot;
        return $model;
    }

    /**
     * @param  string $attribute
     * @return mixed
     */
    public function readAttribute(string $attribute)
    {
        return $this->$attribute;
    }

    /**
     * @param  string $attribute
     * @param  mixed  $value
     * @return void
     */
    public function writeAttribute(string $attribute, $value): void
    {
        $this->$attribute = $value;
    }

    /**
     * @param  \Phalcon\Messages\MessageInterface $message
     * @return \Phalcon\Mvc\ModelInterface
     */
    public function appendMessage(\Phalcon\Messages\MessageInterface $message): \Phalcon\Mvc\ModelInterface
    {
        $this->messages[] = $message->getField();
        $this->texts[] = $message->getMessage();
        return $this;
    }

    /**
     * @return array
     */
    public function getSnapshotData(): array
    {
        return $this->fakeSnapshot;
    }
}
