<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\RankFusion;
use PHPUnit\Framework\TestCase;

/**
 * Reciprocal Rank Fusion of the searchers' rankings (agaia-4666).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RankFusionTest extends TestCase
{
    public function testAnItemSeveralSearchersRankWinsOverOneSearchersTop()
    {
        $fused = (new RankFusion())->fuse(array(
            array('kind' => 'fts', 'keys' => array('issue:a', 'issue:b')),
            array('kind' => 'fuzzy', 'keys' => array('issue:c', 'issue:b')),
        ));

        $this->assertSame(array('issue:b', 'issue:a', 'issue:c'), array_column($fused, 'key'));
        $this->assertSame(array('fts', 'fuzzy'), $fused[0]['matchedBy']);
        $this->assertEqualsWithDelta(2 / 62, $fused[0]['score'], 1e-12);
        $this->assertEqualsWithDelta(1 / 61, $fused[1]['score'], 1e-12);
    }

    public function testExactMatchesStayOnTop()
    {
        $fused = (new RankFusion())->fuse(array(
            array('kind' => 'fts', 'keys' => array('issue:a', 'issue:b')),
            array('kind' => 'fuzzy', 'keys' => array('issue:a', 'issue:b')),
            array('kind' => 'exact', 'keys' => array('issue:z')),
        ));

        $this->assertSame(array('issue:z', 'issue:a', 'issue:b'), array_column($fused, 'key'));
        $this->assertSame(array('exact'), $fused[0]['matchedBy']);
    }

    public function testMoreSearchersFuseTheSameWay()
    {
        $fused = (new RankFusion())->fuse(array(
            array('kind' => 'fts', 'keys' => array('issue:a')),
            array('kind' => 'semantic', 'keys' => array('issue:b', 'issue:a')),
        ));

        $this->assertSame(array('issue:a', 'issue:b'), array_column($fused, 'key'));
        $this->assertSame(array('fts', 'semantic'), $fused[0]['matchedBy']);
    }

    public function testEqualScoresKeepAStableOrder()
    {
        $fused = (new RankFusion())->fuse(array(
            array('kind' => 'fts', 'keys' => array('issue:b')),
            array('kind' => 'fuzzy', 'keys' => array('issue:a')),
        ));

        $this->assertSame(array('issue:a', 'issue:b'), array_column($fused, 'key'));
    }

    public function testNoRankingsFuseToNothing()
    {
        $this->assertSame(array(), (new RankFusion())->fuse(array()));
    }
}
