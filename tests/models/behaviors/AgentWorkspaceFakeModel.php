<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

/**
 * A model stand-in with the attributes the behavior reads.
 */
class AgentWorkspaceFakeModel extends \Phalcon\Mvc\Model
{
    public $workspaceId;
    public $assignee;
    public $projectId;
    public $userId;
    public $runtimeId;
    public $shortCode;
    public $id;

    /**
     * Fields of the messages appended by the behavior.
     *
     * @var array
     */
    public $messages = [];

    /**
     * @var array
     */
    private $fakeSnapshot;

    /**
     * @param array $attributes
     * @param array $snapshot
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

    public function readAttribute(string $attribute)
    {
        return $this->$attribute;
    }

    public function appendMessage(\Phalcon\Messages\MessageInterface $message): \Phalcon\Mvc\ModelInterface
    {
        $this->messages[] = $message->getField();
        return $this;
    }

    public function getSnapshotData(): array
    {
        return $this->fakeSnapshot;
    }
}
