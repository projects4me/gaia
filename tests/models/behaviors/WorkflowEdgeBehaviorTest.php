<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\workflowEdgeBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for workflowEdgeBehavior: an editable workflow and an edge that
 * fits the graph; a delete needs only the editable workflow.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowEdgeBehaviorTest extends TestCase
{
    use WorkflowModelStub;

    public function testCreateAcceptsAnEdgeThatFits()
    {
        $model = $this->edge('run', 'done', 'end');

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testCreateRejectsAnEdgeOfAnActiveWorkflow()
    {
        $model = $this->edge('run', 'done', 'end');

        $this->assertFalse($this->behavior('Active')->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['An active workflow cannot be changed; disable it or set it back to Draft first.'], $model->messages);
    }

    public function testCreateRejectsAnEdgeOnATakenPort()
    {
        $model = $this->edge('t', 'next', 'end');

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(["Port 'next' of node 't' is already connected."], $model->messages);
    }

    public function testUpdateMayKeepItsOwnPort()
    {
        $stored = ['workflowId' => 'wf-1', 'fromNodeId' => 't', 'fromPort' => 'next', 'toNodeId' => 'run'];
        $model = $this->model(array_merge($stored, ['id' => 'e-1', 'toNodeId' => 'end']), $stored);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    public function testDeleteOnlyNeedsAnEditableWorkflow()
    {
        $stored = ['workflowId' => 'wf-1', 'fromNodeId' => 't', 'fromPort' => 'next', 'toNodeId' => 'gone'];
        $model = $this->model(array_merge($stored, ['id' => 'e-1', 'isDeleting' => true]), $stored);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertFalse($this->behavior('Active')->notify('beforeValidationOnUpdate', $model));
    }

    private function edge($from, $port, $to)
    {
        return $this->model(['id' => 'e-new', 'workflowId' => 'wf-1', 'fromNodeId' => $from, 'fromPort' => $port, 'toNodeId' => $to]);
    }

    /**
     * A behaviour over trigger -> run agent -> end, with t:next taken by e-1.
     *
     * @param  string|null $status
     * @return workflowEdgeBehavior
     */
    private function behavior($status = 'Draft')
    {
        return new class ($status) extends workflowEdgeBehavior {
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
                return [
                    ['id' => 't', 'name' => 't', 'category' => 'trigger', 'kind' => 'issue.created', 'config' => []],
                    ['id' => 'run', 'name' => 'run', 'category' => 'actor', 'kind' => 'run_agent', 'config' => ['agentId' => 'a']],
                    ['id' => 'end', 'name' => 'end', 'category' => 'action', 'kind' => 'end', 'config' => []],
                ];
            }

            protected function workflowEdges($workflowId)
            {
                return [['id' => 'e-1', 'fromNodeId' => 't', 'fromPort' => 'next', 'toNodeId' => 'run']];
            }
        };
    }
}
