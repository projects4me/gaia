<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\workflowBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for workflowBehavior: status, scope and owner checks, and the
 * graph check and version count on activation.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowBehaviorTest extends TestCase
{
    use WorkflowModelStub;

    public function testCreateStartsAsADraftAtVersionZero()
    {
        $model = $this->model(['name' => 'Triage', 'version' => 7, 'scope' => ['project-1']]);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame('Draft', $model->status);
        $this->assertSame(0, $model->version);
        $this->assertSame('["project-1"]', $model->scope);
    }

    public function testCreateCannotStartActive()
    {
        $model = $this->model(['name' => 'Triage', 'status' => 'Active', 'scope' => ['project-1']]);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['Add a trigger and its steps before activating a workflow.'], $model->messages);
    }

    /**
     * @dataProvider invalidFields
     */
    public function testRejectsInvalidFields(array $values, $message)
    {
        $model = $this->model(array_merge(['name' => 'Triage', 'status' => 'Draft'], $values));

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([$message], $model->messages);
    }

    public function invalidFields()
    {
        return [
            'status' => [['status' => 'Paused'], 'status must be one of Draft, Active, Disabled.'],
            'scope not a list' => [['scope' => '{"a":1}'], 'scope must be a JSON list of project ids.'],
            'scope project missing' => [['scope' => '["gone"]'], 'scope must list existing projects.'],
            'owner missing' => [['ownerId' => 'gone'], 'ownerId must be an existing user.'],
        ];
    }

    public function testAnActiveWorkflowBelongsToAProject()
    {
        $model = $this->model(['id' => 'wf-1', 'name' => 'Triage', 'status' => 'Active', 'version' => 0, 'scope' => []],
            ['status' => 'Draft', 'version' => 0]);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['An active workflow must belong to at least one project.'], $model->messages);

        $draft = $this->model(['id' => 'wf-2', 'name' => 'Sketch', 'status' => 'Draft', 'version' => 0],
            ['status' => 'Draft', 'version' => 0]);
        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $draft));
    }

    public function testActivatingChecksTheGraph()
    {
        $model = $this->model(['id' => 'wf-1', 'name' => 'Triage', 'status' => 'Active', 'version' => 0,
            'scope' => ['project-1']], ['status' => 'Draft', 'version' => 0]);

        $this->assertFalse($this->behavior([], [])->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['A workflow needs exactly one trigger; it has 0.'], $model->messages);
    }

    public function testActivatingAValidGraphCountsANewVersion()
    {
        $model = $this->model(['id' => 'wf-1', 'name' => 'Triage', 'status' => 'Active', 'version' => 99,
            'scope' => ['project-1']], ['status' => 'Disabled', 'version' => 2]);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(3, $model->version);
    }

    public function testOtherUpdatesKeepTheVersion()
    {
        $model = $this->model(['id' => 'wf-1', 'name' => 'Renamed', 'status' => 'Active', 'version' => 99,
            'scope' => '["project-1"]'], ['status' => 'Active', 'version' => 2]);

        $this->assertNotFalse($this->behavior([], [])->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(2, $model->version);
    }

    /**
     * @param  array|null $nodes the graph; null for a valid one
     * @param  array      $edges
     * @return workflowBehavior
     */
    private function behavior(array $nodes = null, array $edges = [])
    {
        if ($nodes === null) {
            $nodes = [
                ['id' => 't', 'name' => 't', 'category' => 'trigger', 'kind' => 'issue.created', 'config' => []],
                ['id' => 'end', 'name' => 'end', 'category' => 'action', 'kind' => 'end', 'config' => []],
            ];
            $edges = [['id' => 'e', 'fromNodeId' => 't', 'fromPort' => 'next', 'toNodeId' => 'end']];
        }

        return new class ($nodes, $edges) extends workflowBehavior {
            private $nodes;
            private $edges;

            public function __construct(array $nodes, array $edges)
            {
                $this->nodes = $nodes;
                $this->edges = $edges;
            }

            protected function graph($workflowId)
            {
                return [$this->nodes, $this->edges];
            }

            protected function exists($model, $id)
            {
                return $id !== 'gone';
            }
        };
    }
}
