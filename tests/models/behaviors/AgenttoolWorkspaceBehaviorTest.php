<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\CatalogWorkspace;
use Gaia\MVC\Models\Behaviors\agenttoolWorkspaceBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for agenttoolWorkspaceBehavior (Workspaces D-007).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgenttoolWorkspaceBehaviorTest extends TestCase
{
    /**
     * A grant outside the agent's workspace is refused on the server field.
     *
     * @return void
     */
    public function testServerOutsideTheWorkspaceIsRefused()
    {
        $model = GrantFakeModel::grant(['agentId' => 'a1', 'toolServerId' => 's2']);

        $this->assertFalse($this->behavior(false)->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['toolServerId'], $model->messages);
    }

    /**
     * A tool grant outside the workspace is refused on the tool field.
     *
     * @return void
     */
    public function testToolOutsideTheWorkspaceIsRefused()
    {
        $model = GrantFakeModel::grant(['agentId' => 'a1', 'toolId' => 't2']);

        $this->assertFalse($this->behavior(false)->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['toolId'], $model->messages);
    }

    /**
     * A grant inside the workspace passes.
     *
     * @return void
     */
    public function testUpdateToServerOutsideTheWorkspaceIsRefused()
    {
        $model = GrantFakeModel::grant(['agentId' => 'a1', 'toolServerId' => 's2']);

        $this->assertFalse($this->behavior(false)->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['toolServerId'], $model->messages);
    }

    /**
     * A grant of the agent's workspace passes.
     *
     * @return void
     */
    public function testGrantInsideTheWorkspacePasses()
    {
        $model = GrantFakeModel::grant(['agentId' => 'a1', 'toolServerId' => 's1']);

        $this->assertNotFalse($this->behavior(true)->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }

    /**
     * @param  bool $allowed
     * @return agenttoolWorkspaceBehavior
     */
    private function behavior($allowed)
    {
        $rule = new class ($allowed) extends CatalogWorkspace {
            private $allowed;

            public function __construct($allowed)
            {
                $this->allowed = $allowed;
            }

            public function mayGrantTool($agentId, $toolServerId, $toolId)
            {
                return $this->allowed;
            }
        };

        return new agenttoolWorkspaceBehavior($rule);
    }
}

/**
 * A grant stand-in with the attributes the behaviors read.
 */
class GrantFakeModel extends \Phalcon\Mvc\Model
{
    public $agentId;
    public $toolServerId;
    public $toolId;
    public $skillId;
    public $messages = [];

    public static function grant(array $attributes)
    {
        $model = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        foreach ($attributes as $name => $value) {
            $model->$name = $value;
        }
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
}
