<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Builds the SearchService of the user the DI's acl belongs to: exact (number and title), FTS, fuzzy and semantic
 * matching, ranked by StrongestSignal, with the settings of system.search: fuzzyThreshold, semanticMaxDistance and
 * the ranking's threshold, fuzzyWeight, semanticNear and semanticFar. A new kind of matching is added here.
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
     */
    public static function create($di)
    {
        $threshold = self::setting('fuzzyThreshold', FuzzySearcher::DEFAULT_THRESHOLD);
        $maxDistance = self::setting('semanticMaxDistance', SemanticSearcher::DEFAULT_MAX_DISTANCE);
        $ranking = new StrongestSignal(
            self::setting('threshold', StrongestSignal::DEFAULT_THRESHOLD),
            self::setting('fuzzyWeight', StrongestSignal::DEFAULT_FUZZY_WEIGHT),
            $threshold,
            self::setting('semanticNear', StrongestSignal::DEFAULT_SEMANTIC_NEAR),
            self::setting('semanticFar', StrongestSignal::DEFAULT_SEMANTIC_FAR)
        );

        $metaManager = $di->get('metaManager');
        $acl = $di->get('acl');
        $candidates = new CandidateQuery($di->get('db'));
        $tsquery = new TextSearchQuery();

        return new SearchService(
            SearchableRegistry::fromMetadata($metaManager),
            $metaManager,
            $acl,
            new SearchScope($acl, $di->get('modelsManager')),
            array(
                new ExactMatcher($candidates),
                new ExactTitleMatcher($candidates),
                new FtsSearcher($candidates, $tsquery),
                new FuzzySearcher($candidates, $threshold),
                new SemanticSearcher($candidates, new \Gaia\Services\Embeddings(), $maxDistance),
            ),
            $ranking,
            new DisplayLoader($candidates, new Snippet($tsquery))
        );
    }

    /**
     * This function returns a setting of system.search, or its default when the config gives none.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    private static function setting($name, $default)
    {
        global $settings;

        return isset($settings['system']['search'][$name]) ? $settings['system']['search'][$name] : $default;
    }
}
