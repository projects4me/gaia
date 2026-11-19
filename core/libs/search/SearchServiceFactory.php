<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Builds the SearchService of the user the DI's acl belongs to: exact, FTS and fuzzy matching, the fuzzy threshold
 * from system.search.fuzzyThreshold. A new kind of matching (semantic) is added here.
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
            ),
            new RankFusion(),
            new DisplayLoader($candidates, new Snippet($tsquery))
        );
    }
}
