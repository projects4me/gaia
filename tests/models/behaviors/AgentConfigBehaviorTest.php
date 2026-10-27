<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\agentConfigBehavior;

/**
 * Unit tests for agentConfigBehavior: modelConfig is a JSON object, the run
 * limits are positive whole numbers and an agent's userId never changes.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentConfigBehaviorTest extends TestCase
{
    /**
     * A valid config passes and an object modelConfig is stored as JSON text.
     *
     * @return void
     */
    public function testValidConfigPassesAndObjectIsStoredAsJson()
    {
        $model = AgentConfigFakeModel::make(['modelConfig' => ['model' => 'opus'], 'maxTurns' => 40, 'maxRunsPerIssue' => '3']);

        $this->assertNotFalse((new agentConfigBehavior())->notify('beforeValidationOnCreate', $model));
        $this->assertSame('{"model":"opus"}', $model->modelConfig);
        $this->assertSame([], $model->messages);
    }

    /**
     * Empty optional fields are allowed.
     *
     * @return void
     */
    public function testEmptyFieldsPass()
    {
        $model = AgentConfigFakeModel::make(['modelConfig' => null, 'maxTurns' => '', 'maxRunsPerIssue' => null]);

        $this->assertNotFalse((new agentConfigBehavior())->notify('beforeValidationOnCreate', $model));
    }

    /**
     * A modelConfig that is not a JSON object is rejected.
     *
     * @return void
     */
    public function testModelConfigMustBeAJsonObject()
    {
        $model = AgentConfigFakeModel::make(['modelConfig' => 'not json']);

        $this->assertFalse((new agentConfigBehavior())->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['modelConfig'], $model->messages);
    }

    /**
     * The run limits must be positive whole numbers.
     *
     * @return void
     */
    public function testRunLimitsMustBePositiveWholeNumbers()
    {
        foreach ([0, -1, '2.5', 'ten'] as $value) {
            $model = AgentConfigFakeModel::make(['maxRunsPerIssue' => $value]);

            $this->assertFalse((new agentConfigBehavior())->notify('beforeValidationOnCreate', $model));
            $this->assertSame(['maxRunsPerIssue'], $model->messages);
        }
    }

    /**
     * An update cannot move the agent to another user.
     *
     * @return void
     */
    public function testUpdateCannotChangeTheUser()
    {
        $model = AgentConfigFakeModel::make(['userId' => 'user-2'], ['userId' => 'user-1']);

        $this->assertFalse((new agentConfigBehavior())->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['userId'], $model->messages);
    }

    /**
     * An update that keeps the user checks the config as usual.
     *
     * @return void
     */
    public function testUpdateKeepingTheUserChecksTheConfig()
    {
        $model = AgentConfigFakeModel::make(['userId' => 'user-1', 'maxTurns' => 0], ['userId' => 'user-1']);

        $this->assertFalse((new agentConfigBehavior())->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['maxTurns'], $model->messages);
    }
}

/**
 * The model calls agentConfigBehavior makes, without a DI container.
 */
class AgentConfigFakeModel extends \Phalcon\Mvc\Model
{
    public $userId;
    public $modelConfig;
    public $maxTurns;
    public $maxRunsPerIssue;

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

    public function writeAttribute(string $attribute, $value): void
    {
        $this->$attribute = $value;
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
