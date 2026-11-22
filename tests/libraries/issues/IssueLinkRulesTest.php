<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Issues;

use Gaia\Libraries\Issues\IssueGraph;
use Gaia\Libraries\Issues\IssueLinkRules;
use Gaia\Libraries\Issues\IssueLinks;
use Gaia\Libraries\Issues\LinkEdgeSource;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for IssueLinkRules over fake link data and a fake graph. The
 * walk is covered by IssueGraphTest, the 422s on the API by the api-tester
 * cases.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueLinkRulesTest extends TestCase
{
    /**
     * @var array issue id => place
     */
    private $places = [
        'a' => ['projectId' => 'p1', 'workspaceId' => 'w1'],
        'b' => ['projectId' => 'p1', 'workspaceId' => 'w1'],
        'c' => ['projectId' => 'p2', 'workspaceId' => 'w1'],
        'd' => ['projectId' => 'p3', 'workspaceId' => 'w2'],
        'e' => ['projectId' => 'p4', 'workspaceId' => null],
        'f' => ['projectId' => 'p5', 'workspaceId' => null],
        'g' => ['projectId' => 'p4', 'workspaceId' => null],
    ];

    /**
     * A link inside one project passes and reports the project twice.
     *
     * @return void
     */
    public function testLinkInOneProjectPasses()
    {
        $result = $this->rules()->check('a', 'b', IssueLinkRules::BLOCKS);

        $this->assertNull($result['error']);
        $this->assertSame('p1', $result['projectId']);
        $this->assertSame('p1', $result['targetProjectId']);
    }

    /**
     * Projects of one workspace link; the target's project is reported.
     *
     * @return void
     */
    public function testLinkAcrossProjectsOfOneWorkspacePasses()
    {
        $result = $this->rules()->check('a', 'c', IssueLinkRules::RELATES);

        $this->assertNull($result['error']);
        $this->assertSame('p1', $result['projectId']);
        $this->assertSame('p2', $result['targetProjectId']);
    }

    /**
     * Projects of different workspaces do not link.
     *
     * @return void
     */
    public function testLinkAcrossWorkspacesIsRefused()
    {
        $this->assertRefused(IssueLinkRules::OTHER_WORKSPACE, 'a', 'd', IssueLinkRules::RELATES);
    }

    /**
     * Two projects without a workspace do not share one.
     *
     * @return void
     */
    public function testProjectsWithoutWorkspaceDoNotLink()
    {
        $this->assertRefused(IssueLinkRules::OTHER_WORKSPACE, 'e', 'f', IssueLinkRules::RELATES);
        $this->assertNull($this->rules()->check('e', 'g', IssueLinkRules::RELATES)['error']);
    }

    /**
     * Only the three types are links.
     *
     * @return void
     */
    public function testUnknownTypeIsRefused()
    {
        $this->assertRefused(IssueLinkRules::UNKNOWN_TYPE, 'a', 'b', 'duplicates');
    }

    /**
     * An issue does not link to itself.
     *
     * @return void
     */
    public function testSelfLinkIsRefused()
    {
        $this->assertRefused(IssueLinkRules::SELF_LINK, 'a', 'a', IssueLinkRules::RELATES);
    }

    /**
     * Both issues must exist.
     *
     * @return void
     */
    public function testMissingIssueIsRefused()
    {
        $this->assertRefused(IssueLinkRules::ISSUE_MISSING, 'a', 'zz', IssueLinkRules::RELATES);
    }

    /**
     * The same pair and type is stored once; relates in either direction.
     *
     * @return void
     */
    public function testDuplicateIsRefused()
    {
        $links = $this->links([['a', 'b', IssueLinkRules::RELATES]]);

        $this->assertRefused(IssueLinkRules::DUPLICATE, 'b', 'a', IssueLinkRules::RELATES, $links);
        $this->assertRefused(IssueLinkRules::DUPLICATE, 'a', 'b', IssueLinkRules::RELATES, $links);
        $this->assertNull($this->rules($links)->check('a', 'b', IssueLinkRules::BLOCKS)['error']);
    }

    /**
     * A blocks link is a duplicate only in its own direction.
     *
     * @return void
     */
    public function testBlocksDuplicateIsDirected()
    {
        $links = $this->links([['a', 'b', IssueLinkRules::BLOCKS]]);

        $this->assertRefused(IssueLinkRules::DUPLICATE, 'a', 'b', IssueLinkRules::BLOCKS, $links);
        $this->assertNull($this->rules($links)->check('b', 'a', IssueLinkRules::BLOCKS)['error']);
    }

    /**
     * A blocks cycle is refused; the graph walks the blocks links.
     *
     * @return void
     */
    public function testBlocksCycleIsRefused()
    {
        $types = [];
        $rules = new IssueLinkRules($this->links(), function ($type) use (&$types) {
            $types[] = $type;
            return $this->graph(IssueGraph::CYCLE);
        });

        $this->assertSame(IssueLinkRules::BLOCKS_CYCLE, $rules->check('a', 'b', IssueLinkRules::BLOCKS)['error']);
        $this->assertSame([IssueLinkRules::BLOCKS], $types);
    }

    /**
     * A splits cycle is refused.
     *
     * @return void
     */
    public function testSplitsCycleIsRefused()
    {
        $rules = $this->rules(null, IssueGraph::CYCLE);

        $this->assertSame(IssueLinkRules::SPLITS_CYCLE, $rules->check('a', 'b', IssueLinkRules::SPLITS)['error']);
    }

    /**
     * A walk that hits the depth cap refuses the link.
     *
     * @return void
     */
    public function testTooDeepIsRefused()
    {
        $rules = $this->rules(null, IssueGraph::TOO_DEEP);

        $this->assertSame(IssueLinkRules::TOO_DEEP, $rules->check('a', 'b', IssueLinkRules::BLOCKS)['error']);
    }

    /**
     * An issue is split from one source at most.
     *
     * @return void
     */
    public function testSecondSplitSourceIsRefused()
    {
        $links = $this->links([['c', 'b', IssueLinkRules::SPLITS]]);

        $this->assertRefused(IssueLinkRules::ALREADY_SPLIT, 'a', 'b', IssueLinkRules::SPLITS, $links);
    }

    /**
     * Relates links are not walked for cycles.
     *
     * @return void
     */
    public function testRelatesIsNotWalked()
    {
        $rules = $this->rules(null, IssueGraph::CYCLE);

        $this->assertNull($rules->check('a', 'b', IssueLinkRules::RELATES)['error']);
    }

    /**
     * The edge source refuses a type outside the fixed list, and walks only
     * the live links of its type.
     *
     * @return void
     */
    public function testEdgeSourceTakesOnlyKnownTypes()
    {
        $this->assertStringContainsString('"type" = \'splits\'', (new LinkEdgeSource('splits'))->edgesSql());

        $this->expectException(\InvalidArgumentException::class);
        new LinkEdgeSource("x' OR '1'='1");
    }

    /**
     * @param  string          $error
     * @param  string          $source
     * @param  string          $target
     * @param  string          $type
     * @param  IssueLinks|null $links
     * @return void
     */
    private function assertRefused($error, $source, $target, $type, $links = null)
    {
        $result = $this->rules($links)->check($source, $target, $type);

        $this->assertSame($error, $result['error']);
        $this->assertNull($result['projectId']);
    }

    /**
     * @param  IssueLinks|null $links
     * @param  string          $graphResult
     * @return IssueLinkRules
     */
    private function rules($links = null, $graphResult = IssueGraph::ACYCLIC)
    {
        return new IssueLinkRules($links ?? $this->links(), function () use ($graphResult) {
            return $this->graph($graphResult);
        });
    }

    /**
     * @param  array $stored [source, target, type] rows
     * @return IssueLinks
     */
    private function links(array $stored = [])
    {
        return new class ($this->places, $stored) extends IssueLinks {
            private $places;

            private $stored;

            public function __construct(array $places, array $stored)
            {
                $this->places = $places;
                $this->stored = $stored;
            }

            public function placeOf($issueId)
            {
                return $this->places[$issueId] ?? null;
            }

            public function exists($sourceIssueId, $targetIssueId, $type, $eitherDirection = false)
            {
                foreach ($this->stored as [$source, $target, $storedType]) {
                    if ($storedType !== $type) {
                        continue;
                    }
                    if ($source === $sourceIssueId && $target === $targetIssueId) {
                        return true;
                    }
                    if ($eitherDirection && $source === $targetIssueId && $target === $sourceIssueId) {
                        return true;
                    }
                }
                return false;
            }

            public function hasIncoming($targetIssueId, $type)
            {
                foreach ($this->stored as [, $target, $storedType]) {
                    if ($target === $targetIssueId && $storedType === $type) {
                        return true;
                    }
                }
                return false;
            }
        };
    }

    /**
     * @param  string $result What check() answers
     * @return IssueGraph
     */
    private function graph($result)
    {
        return new class ($result) extends IssueGraph {
            private $result;

            public function __construct($result)
            {
                parent::__construct(new LinkEdgeSource('blocks'), 10, new \stdClass());
                $this->result = $result;
            }

            public function check($from, $to)
            {
                return $this->result;
            }
        };
    }
}
