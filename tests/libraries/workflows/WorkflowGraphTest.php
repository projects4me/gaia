<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\WorkflowGraph;
use PHPUnit\Framework\TestCase;

/**
 * Tests the workflow graph checks: one trigger, valid and free ports, every
 * port connected, every node reached, and no loops.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowGraphTest extends TestCase
{
    public function testAcceptsAGraphWhosePortsAllLeadSomewhere()
    {
        $this->assertSame([], $this->graph()->errors($this->nodes(), $this->edges()));
    }

    public function testRequiresExactlyOneTrigger()
    {
        $nodes = $this->nodes();
        $nodes[] = $this->node('t2', 'trigger', 'issue.created');

        $errors = $this->graph()->errors($nodes, $this->edges());

        $this->assertContains('A workflow needs exactly one trigger; it has 2.', $errors);
        $this->assertContains(
            'A workflow needs exactly one trigger; it has 0.',
            $this->graph()->errors([$this->node('end', 'action', 'end')], [])
        );
    }

    public function testReportsAnUnconnectedPortAndAnUnreachedNode()
    {
        $edges = array_values(array_filter($this->edges(), function ($edge) {
            return $edge['fromPort'] !== 'blocked';
        }));
        $nodes = array_merge($this->nodes(), [$this->node('lost', 'action', 'end')]);

        $errors = $this->graph()->errors($nodes, $edges);

        $this->assertContains(
            "Port 'blocked' of node 'run' is not connected; connect it or end it with an end node.",
            $errors
        );
        $this->assertContains("node 'lost' is not reached from the trigger.", $errors);
    }

    public function testRejectsALoop()
    {
        $nodes = [
            $this->node('t', 'trigger', 'issue.created'),
            $this->node('a', 'action', 'set_status', ['status' => 'Open']),
            $this->node('b', 'action', 'add_comment', ['body' => 'x']),
        ];
        $edges = [
            $this->edge('t', 'next', 'a'),
            $this->edge('a', 'next', 'b'),
            $this->edge('b', 'next', 'a'),
        ];

        $this->assertContains('A workflow cannot loop back on itself.', $this->graph()->errors($nodes, $edges));
    }

    public function testSwitchHasAPortPerCaseAndDefault()
    {
        $nodes = [
            $this->node('t', 'trigger', 'issue.created'),
            $this->node('s', 'condition', 'switch', ['cases' => [
                ['key' => 'bug', 'conditions' => [['field' => 'type', 'operator' => 'equals', 'value' => 'Bug']]],
            ]]),
            $this->node('end', 'action', 'end'),
        ];
        $edges = [$this->edge('t', 'next', 's'), $this->edge('s', 'bug', 'end')];

        $this->assertSame(
            ["Port 'default' of node 's' is not connected; connect it or end it with an end node."],
            $this->graph()->errors($nodes, $edges)
        );
    }

    /**
     * @dataProvider invalidEdges
     */
    public function testRejectsAnEdgeThatDoesNotFit(array $edge, $message)
    {
        $errors = $this->graph()->edgeErrors($this->nodes(), [$this->edge('t', 'next', 'run')], $edge);

        $this->assertContains($message, $errors);
    }

    public function invalidEdges()
    {
        return [
            'unknown port' => [$this->edge('run', 'maybe', 'end'), "'maybe' is not a port of node 'run' (done, needs_input, blocked, failed, limit)."],
            'end has no ports' => [$this->edge('end', 'next', 'run'), "'next' is not a port of node 'end'; it has none."],
            'port in use' => [$this->edge('t', 'next', 'end'), "Port 'next' of node 't' is already connected."],
            'into trigger' => [$this->edge('run', 'done', 't'), 'An edge cannot lead into the trigger.'],
            'to itself' => [$this->edge('run', 'done', 'run'), 'An edge cannot join a node to itself.'],
            'other workflow' => [$this->edge('run', 'done', 'elsewhere'), 'An edge must join two nodes of the same workflow.'],
        ];
    }

    public function testRejectsAnEdgeThatClosesALoop()
    {
        $nodes = [
            $this->node('t', 'trigger', 'issue.created'),
            $this->node('a', 'action', 'set_status', ['status' => 'Open']),
            $this->node('b', 'action', 'add_comment', ['body' => 'x']),
        ];
        $others = [$this->edge('t', 'next', 'a'), $this->edge('a', 'next', 'b')];

        $this->assertSame(
            ['This edge would make the workflow loop back on itself.'],
            $this->graph()->edgeErrors($nodes, $others, $this->edge('b', 'next', 'a'))
        );
        $this->assertSame([], $this->graph()->edgeErrors($nodes, [$this->edge('t', 'next', 'a')], $this->edge('a', 'next', 'b')));
    }

    /**
     * Trigger -> run agent, every outcome ending at the end node.
     *
     * @return array
     */
    private function nodes()
    {
        return [
            $this->node('t', 'trigger', 'issue.assignee.changed'),
            $this->node('run', 'actor', 'run_agent', ['agentId' => 'agent-1']),
            $this->node('end', 'action', 'end'),
        ];
    }

    /**
     * @return array
     */
    private function edges()
    {
        $edges = [$this->edge('t', 'next', 'run')];
        foreach (['done', 'needs_input', 'blocked', 'failed', 'limit'] as $port) {
            $edges[] = $this->edge('run', $port, 'end');
        }

        return $edges;
    }

    private function node($id, $category, $kind, array $config = [])
    {
        return ['id' => $id, 'name' => $id, 'category' => $category, 'kind' => $kind, 'config' => $config];
    }

    private function edge($from, $port, $to)
    {
        return ['id' => "{$from}-{$port}", 'fromNodeId' => $from, 'fromPort' => $port, 'toNodeId' => $to];
    }

    private function graph()
    {
        return new WorkflowGraph();
    }
}
