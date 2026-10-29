<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workflows\NodeConfigRules;
use Gaia\MVC\Models\Behaviors\workflowNodeBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for workflowNodeBehavior: an editable workflow, a known kind, a
 * valid config, one trigger, and layout changes on an active workflow.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowNodeBehaviorTest extends TestCase
{
    use WorkflowModelStub;

    public function testCreateAcceptsAValidNodeAndStoresItsConfigAsJson()
    {
        $model = $this->node(['category' => 'actor', 'kind' => 'run_agent', 'config' => ['agentId' => 'agent-1']]);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame('{"agentId":"agent-1"}', $model->config);
        $this->assertSame([], $model->messages);
    }

    /**
     * @dataProvider invalidNodes
     */
    public function testCreateRejectsAnInvalidNode(array $values, $message, $status = 'Draft')
    {
        $model = $this->node($values);

        $this->assertFalse($this->behavior($status)->notify('beforeValidationOnCreate', $model));
        $this->assertContains($message, $model->messages);
    }

    public function invalidNodes()
    {
        return [
            'active workflow' => [['category' => 'action', 'kind' => 'end'],
                'An active workflow cannot be changed; disable it or set it back to Draft first.', 'Active'],
            'missing workflow' => [['category' => 'action', 'kind' => 'end'], 'workflowId must be an existing workflow.', null],
            'category' => [['category' => 'step', 'kind' => 'end'], 'category must be one of trigger, condition, actor, action.'],
            'kind' => [['category' => 'trigger', 'kind' => 'run_agent'], 'run_agent is not a kind of trigger node.'],
            'config not an object' => [['category' => 'action', 'kind' => 'end', 'config' => '[1]'], 'config must be a JSON object.'],
            'config' => [['category' => 'actor', 'kind' => 'run_agent', 'config' => '{}'], 'config.agentId is required for run_agent.'],
            'second trigger' => [['category' => 'trigger', 'kind' => 'issue.created'], 'A workflow can have only one trigger.'],
        ];
    }

    public function testUpdateMayMoveANodeOfAnActiveWorkflow()
    {
        $stored = ['workflowId' => 'wf-1', 'category' => 'action', 'kind' => 'end', 'config' => '{}', 'positionX' => 1];
        $model = $this->model(array_merge($stored, ['id' => 'n-1', 'positionX' => 300]), $stored);

        $this->assertNotFalse($this->behavior('Active')->notify('beforeValidationOnUpdate', $model));
    }

    public function testUpdateCannotChangeTheGraphOfAnActiveWorkflow()
    {
        $stored = ['workflowId' => 'wf-1', 'category' => 'action', 'kind' => 'set_status', 'config' => '{"status":"Open"}'];
        $model = $this->model(array_merge($stored, ['id' => 'n-1', 'config' => ['status' => 'Closed']]), $stored);

        $this->assertFalse($this->behavior('Active')->notify('beforeValidationOnUpdate', $model));
    }

    public function testUpdateCannotMoveANodeToAnotherWorkflow()
    {
        $stored = ['workflowId' => 'wf-1', 'category' => 'action', 'kind' => 'end', 'config' => '{}'];
        $model = $this->model(array_merge($stored, ['id' => 'n-1', 'workflowId' => 'wf-2']), $stored);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['The workflow of a node cannot be changed.'], $model->messages);
    }

    public function testTheTriggerMayBeSavedAgain()
    {
        $stored = ['workflowId' => 'wf-1', 'category' => 'trigger', 'kind' => 'issue.created', 'config' => '{}'];
        $model = $this->model(array_merge($stored, ['id' => 'trigger-1', 'kind' => 'issue.modified']), $stored);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    private function node(array $values)
    {
        return $this->model(array_merge(['id' => 'n-new', 'workflowId' => 'wf-1'], $values));
    }

    /**
     * A behaviour whose workflow has the given status and a trigger node.
     *
     * @param  string|null $status
     * @return workflowNodeBehavior
     */
    private function behavior($status = 'Draft')
    {
        return new class ($status) extends workflowNodeBehavior {
            private $status;

            public function __construct($status)
            {
                $this->status = $status;
            }

            protected function workflowStatus($workflowId)
            {
                return $this->status;
            }

            protected function workflowNodes($workflowId)
            {
                return [['id' => 'trigger-1', 'name' => 't', 'category' => 'trigger', 'kind' => 'issue.created', 'config' => []]];
            }

            protected function configRules()
            {
                return new class () extends NodeConfigRules {
                    protected function exists($model, $id)
                    {
                        return true;
                    }
                };
            }
        };
    }
}
