<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Builds the SearchService of the user the DI's acl belongs to: exact, FTS, fuzzy and semantic matching, the fuzzy
 * threshold from system.search.fuzzyThreshold and the semantic maximum distance from
 * system.search.semanticMaxDistance. A new kind of matching is added here.
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
        global $settings;
        $threshold = isset($settings['system']['search']['fuzzyThreshold'])
            ? $settings['system']['search']['fuzzyThreshold']
            : FuzzySearcher::DEFAULT_THRESHOLD;
        $maxDistance = isset($settings['system']['search']['semanticMaxDistance'])
            ? $settings['system']['search']['semanticMaxDistance']
            : SemanticSearcher::DEFAULT_MAX_DISTANCE;

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
                new FtsSearcher($candidates, $tsquery),
                new FuzzySearcher($candidates, $threshold),
                new SemanticSearcher($candidates, new \Gaia\Services\Embeddings(), $maxDistance),
            ),
            new RankFusion(),
            new DisplayLoader($candidates, new Snippet($tsquery))
        );
    }
}
