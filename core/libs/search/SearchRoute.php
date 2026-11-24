<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Picks the searchers a query runs, by its shape, as stages: the search runs a stage only when the stages before it
 * found nothing.
 *
 * - A number alone (4170, #4170) is a lookup: the record with that number, or else (two digits or more) the numbers
 *   starting or ending with it. Never a text match, so a number nobody has finds nothing.
 * - A reference with a known prefix (agaia-4170) is a lookup of that record alone. A prefix no record the user may
 *   see has is plain text, e.g. covid-19.
 * - Anything else is text: every text searcher, the exact one with the numbers the query refers to.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchRoute
{
    /**
     * @var Searcher[]
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
     * @param Searcher[] $text The searchers of a text query, in order
     * @param ExactMatcher $exact The number lookup
     * @param PartialNumberMatcher $partial The partial number lookup, when no number equals the query
     * @param ReferenceMatcher $reference The reference lookup
     */
    public function __construct(
        array $text,
        ExactMatcher $exact,
        PartialNumberMatcher $partial,
        ReferenceMatcher $reference
    ) {
        $this->text = $text;
        $this->exact = $exact;
        $this->partial = $partial;
        $this->reference = $reference;
    }

    /**
     * This function returns the searchers of a text query.
     *
     * @return Searcher[]
     */
    public function text()
    {
        return $this->text;
    }

    /**
     * This function returns the stages of searchers the query runs, in order.
     *
     * @param SearchQuery $query
     * @param callable $knowsReference function (ReferenceMatcher $reference): bool, whether a type searched knows
     *     the query's reference prefix
     * @return array A list of Searcher[]
     */
    public function stages(SearchQuery $query, callable $knowsReference)
    {
        if ($query->number() !== null) {
            return array(array($this->exact), array($this->partial));
        }
        if ($query->reference() !== null && $knowsReference($this->reference)) {
            return array(array($this->reference));
        }

        return array($this->text);
    }
}
