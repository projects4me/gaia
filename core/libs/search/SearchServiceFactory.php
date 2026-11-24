<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Builds the SearchService of the user the DI's acl belongs to: exact (number and title), FTS, fuzzy and semantic
 * matching for text, number, partial number and reference lookups (SearchRoute), ranked by StrongestSignal, with the
 * settings of system.search: fuzzyThreshold, semanticMaxDistance, candidateDepth, numberQueryWords and the ranking's
 * threshold, fuzzyWeight, semanticNear, semanticFar, allWordsBoundary and partialNumberBoundary. Every
 * setting is required:
 * the values live in the config only. A new kind of matching is added here.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchServiceFactory
{
    /**
     * @param \Phalcon\Di\DiInterface $di
     * @return SearchService
     * @throws \Gaia\Exception\Exception When a setting is missing or out of bounds
     */
    public static function create($di)
    {
        $threshold = self::setting('fuzzyThreshold');
        $maxDistance = self::setting('semanticMaxDistance');
        $candidateDepth = self::setting('candidateDepth');
        $numberQueryWords = self::setting('numberQueryWords');
        $ranking = new StrongestSignal(
            self::setting('threshold'),
            self::setting('fuzzyWeight'),
            $threshold,
            self::setting('semanticNear'),
            self::setting('semanticFar'),
            self::setting('allWordsBoundary'),
            self::setting('partialNumberBoundary')
        );

        $metaManager = $di->get('metaManager');
        $acl = $di->get('acl');
        $candidates = new CandidateQuery($di->get('db'));
        $tsquery = new TextSearchQuery();
        $exact = new ExactMatcher($candidates, $numberQueryWords);

        return new SearchService(
            SearchableRegistry::fromMetadata($metaManager),
            $metaManager,
            $acl,
            new SearchScope($acl, $di->get('modelsManager')),
            new SearchRoute(
                array(
                    $exact,
                    new ExactTitleMatcher($candidates),
                    new FtsSearcher($candidates, $tsquery),
                    new FuzzySearcher($candidates, $threshold),
                    new SemanticSearcher($candidates, new \Gaia\Services\Embeddings(), $maxDistance),
                ),
                $exact,
                new PartialNumberMatcher($candidates),
                new ReferenceMatcher($candidates)
            ),
            $ranking,
            new UnknownWords($candidates, $tsquery),
            new DisplayLoader($candidates, new Snippet($tsquery)),
            $candidateDepth
        );
    }

    /**
     * This function returns a setting of system.search.
     *
     * @param string $name
     * @return mixed
     * @throws \Gaia\Exception\Exception When the config does not give the setting
     */
    private static function setting($name)
    {
        global $settings;

        if (!isset($settings['system']['search'][$name])) {
            throw new \Gaia\Exception\Exception("The search setting system.search.{$name} is missing from the config.");
        }

        return $settings['system']['search'][$name];
    }
}
