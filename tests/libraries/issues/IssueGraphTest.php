<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Issues;

use Gaia\Libraries\Issues\IssueEdgeSource;
use Gaia\Libraries\Issues\IssueGraph;
use Gaia\Libraries\Issues\ParentEdgeSource;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for IssueGraph. The recursive CTE runs on the test database
 * over a fixed VALUES list of edges, so no rows are written.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueGraphTest extends TestCase
{
    /**
     * A chain d -> c -> b -> a (each issue points to its parent).
     *
     * @var array
     */
    const CHAIN = [['d', 'c'], ['c', 'b'], ['b', 'a']];

    /**
     * An edge from an issue to itself is a cycle, without a query.
     *
     * @return void
     */
    public function testSelfEdgeIsACycle()
    {
        $graph = new IssueGraph($this->edges([]), 10, new \stdClass());

        $this->assertSame(IssueGraph::CYCLE, $graph->check('a', 'a'));
        $this->assertTrue($graph->wouldCreateCycle('a', 'a'));
    }

    /**
     * Pointing a parent at its direct child closes a cycle.
     *
     * @return void
     */
    public function testEdgeToDirectChildIsACycle()
    {
        $this->assertSame(IssueGraph::CYCLE, $this->graph(self::CHAIN)->check('c', 'd'));
    }

    /**
     * Pointing the root at a deep descendant closes a cycle.
     *
     * @return void
     */
    public function testEdgeToDeepDescendantIsACycle()
    {
        $this->assertSame(IssueGraph::CYCLE, $this->graph(self::CHAIN)->check('a', 'd'));
    }

    /**
     * Edges to ancestors, siblings or unrelated issues are fine.
     *
     * @return void
     */
    public function testEdgeOutsideTheDescendantsIsAcyclic()
    {
        $graph = $this->graph(array_merge(self::CHAIN, [['e', 'a']]));

        $this->assertSame(IssueGraph::ACYCLIC, $graph->check('d', 'a'));
        $this->assertSame(IssueGraph::ACYCLIC, $graph->check('d', 'e'));
        $this->assertSame(IssueGraph::ACYCLIC, $graph->check('x', 'd'));
        $this->assertFalse($graph->wouldCreateCycle('c', 'e'));
    }

    /**
     * A walk that reaches the depth cap without finding the source cannot
     * prove the edge acyclic.
     *
     * @return void
     */
    public function testWalkPastTheDepthCapIsTooDeep()
    {
        $graph = $this->graph(self::CHAIN, 2);

        $this->assertSame(IssueGraph::TOO_DEEP, $graph->check('x', 'd'));
        $this->assertTrue($graph->wouldCreateCycle('x', 'd'));
        $this->assertSame(IssueGraph::CYCLE, $graph->check('b', 'd'));
    }

    /**
     * A cycle already in the data does not make the walk run forever.
     *
     * @return void
     */
    public function testExistingCycleStopsAtTheDepthCap()
    {
        $graph = $this->graph([['a', 'b'], ['b', 'a']], 50);

        $this->assertSame(IssueGraph::TOO_DEEP, $graph->check('x', 'a'));
    }

    /**
     * The parent edge source runs against the issues table.
     *
     * @return void
     */
    public function testParentEdgeSourceRunsOnTheIssuesTable()
    {
        $graph = new IssueGraph(new ParentEdgeSource(), 10, $this->db());

        $this->assertSame(IssueGraph::ACYCLIC, $graph->check('no-such-issue-1', 'no-such-issue-2'));
    }

    /**
     * The lock needs a transaction, and is held inside one.
     *
     * @return void
     */
    public function testLockNeedsATransaction()
    {
        $db = $this->db();
        $graph = new IssueGraph($this->edges([]), 10, $db);

        $db->begin();
        try {
            $graph->lock();
            $held = $db->fetchColumn(
                'SELECT COUNT(*) FROM pg_locks WHERE locktype = \'advisory\' AND pid = pg_backend_pid()'
            );
        } finally {
            $db->rollback();
        }
        $this->assertSame(1, (int) $held);

        $this->expectException(\LogicException::class);
        $graph->lock();
    }

    /**
     * @param  array $edges [source, target] pairs
     * @param  int   $maxDepth
     * @return IssueGraph
     */
    private function graph(array $edges, $maxDepth = 10)
    {
        return new IssueGraph($this->edges($edges), $maxDepth, $this->db());
    }

    /**
     * @param  array $edges [source, target] pairs of plain letters
     * @return IssueEdgeSource
     */
    private function edges(array $edges)
    {
        return new class ($edges) implements IssueEdgeSource {
            private $edges;

            public function __construct(array $edges)
            {
                $this->edges = $edges;
            }

            public function edgesSql()
            {
                if ($this->edges === []) {
                    return 'SELECT CAST(NULL AS varchar) AS "source", CAST(NULL AS varchar) AS "target" WHERE false';
                }
                $rows = array_map(function ($edge) {
                    return "('" . $edge[0] . "', '" . $edge[1] . "')";
                }, $this->edges);

                return 'SELECT "source", "target" FROM (VALUES ' . implode(', ', $rows) . ') AS "e"("source", "target")';
            }
        };
    }

    /**
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    private function db()
    {
        return \Phalcon\Di\Di::getDefault()->get('db');
    }
}
