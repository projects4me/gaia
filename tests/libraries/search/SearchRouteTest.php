<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\ExactMatcher;
use Gaia\Libraries\Search\PartialNumberMatcher;
use Gaia\Libraries\Search\ReferenceMatcher;
use Gaia\Libraries\Search\SearchQuery;
use Gaia\Libraries\Search\SearchRoute;
use Gaia\Libraries\Search\Searcher;
use PHPUnit\Framework\TestCase;

/**
 * Picking the searchers a query runs by its shape (agaia-4890, agaia-4920).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchRouteTest extends TestCase
{
    /**
     * @var Searcher
     */
    private $text;

    /**
     * @var ExactMatcher
     */
    private $exact;

    /**
     * @var PartialNumberMatcher
     */
    private $partial;

    /**
     * @var ReferenceMatcher
     */
    private $reference;

    /**
     * @var SearchRoute
     */
    private $route;

    protected function setUp(): void
    {
        $this->text = $this->createMock(Searcher::class);
        $this->exact = $this->createMock(ExactMatcher::class);
        $this->partial = $this->createMock(PartialNumberMatcher::class);
        $this->reference = $this->createMock(ReferenceMatcher::class);
        $this->route = new SearchRoute(
            array($this->exact, $this->text),
            $this->exact,
            $this->partial,
            $this->reference
        );
    }

    public function testANumberRunsTheExactAndThePartialNumbersInOneStage()
    {
        foreach (array('48', '#48', '4170') as $query) {
            $this->assertSame(
                array(array($this->exact, $this->partial)),
                $this->route->stages(new SearchQuery($query), $this->knows(true)),
                $query
            );
        }
    }

    public function testAKnownReferenceRunsTheReferenceAlone()
    {
        $this->assertSame(
            array(array($this->reference)),
            $this->route->stages(new SearchQuery('agaia-4170'), $this->knows(true))
        );
    }

    public function testAnUnknownReferenceAndTextRunTheTextSearchers()
    {
        foreach (array('covid-19', 'login fails 4170') as $query) {
            $this->assertSame(
                array(array($this->exact, $this->text)),
                $this->route->stages(new SearchQuery($query), $this->knows(false)),
                $query
            );
        }
    }

    /**
     * @param bool $known
     * @return callable
     */
    private function knows($known)
    {
        return function (ReferenceMatcher $reference) use ($known) {
            return $known;
        };
    }
}
