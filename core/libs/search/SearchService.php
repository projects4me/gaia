<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Security\Acl;

/**
 * Module-agnostic search: parses the query once, runs every searcher over each requested type, inside what the user
 * may see, and ranks what they found by its strongest signal, records matching every word first. A "phrase" or
 * -word in a short query skips the fuzzy and meaning searches. A query without a letter or a digit finds nothing.
 *
 * Each type needs <module>.get (a denied type denies the whole request, as unauthorized query criteria do, D-027).
 * Every candidate query stays inside the module's record scope and deleted = 0 before ranking and LIMIT (D-022).
 * A searcher is skipped for a type when the user may not read a field it matches, and a display field the user may
 * not read is left out of the results. A searcher whose service is down (semantic without Ares) is left out of the
 * search, logged and reported unavailable in the results, so they are never a silent partial answer.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchService
{
    /**
     * @var SearchableRegistry
     */
    private $registry;

    /**
     * @var Manager
     */
    private $metaManager;

    /**
     * @var Acl
     */
    private $acl;

    /**
     * @var SearchScope
     */
    private $scope;

    /**
     * @var Searcher[]
     */
    private $searchers;

    /**
     * @var StrongestSignal
     */
    private $ranking;

    /**
     * @var DisplayLoader
     */
    private $display;

    /**
     * How many candidates each searcher gives per type: the ranking needs more than the limit to reorder.
     *
     * @var int
     */
    private $candidateDepth;

    /**
     * @param SearchableRegistry $registry
     * @param Manager $metaManager
     * @param Acl $acl The ACL of the user searching
     * @param SearchScope $scope
     * @param Searcher[] $searchers
     * @param StrongestSignal $ranking
     * @param DisplayLoader $display
     * @param int $candidateDepth How many candidates each searcher gives per type, at least 1
     * @throws \Gaia\Exception\Exception When the candidate depth is not a positive whole number
     */
    public function __construct(
        SearchableRegistry $registry,
        Manager $metaManager,
        Acl $acl,
        SearchScope $scope,
        array $searchers,
        StrongestSignal $ranking,
        DisplayLoader $display,
        $candidateDepth
    ) {
        if (filter_var($candidateDepth, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
            throw new \Gaia\Exception\Exception('The search candidate depth must be a whole number from 1.');
        }
        $this->registry = $registry;
        $this->metaManager = $metaManager;
        $this->acl = $acl;
        $this->scope = $scope;
        $this->searchers = $searchers;
        $this->ranking = $ranking;
        $this->display = $display;
        $this->candidateDepth = (int) $candidateDepth;
    }

    /**
     * This function searches the requested types and returns the results, best first.
     *
     * @param SearchRequest $request
     * @return SearchResults
     * @throws \Gaia\Exception\BadRequest When a type is not searchable
     * @throws \Gaia\Exception\Access When the user may not get a type
     */
    public function search(SearchRequest $request)
    {
        // Authorize every type before searching any
        $entries = array();
        foreach ($request->types() as $type) {
            $entry = $this->registry->forType($type);
            if ($entry === null) {
                throw new \Gaia\Exception\BadRequest("'{$type}' is not a search type.");
            }
            $this->acl->checkModelAccess($entry['model'], 'get');
            $entries[$type] = $entry;
        }

        $query = new SearchQuery($request->query());
        if (!$query->hasWords()) {
            return new SearchResults(array(), array());
        }

        $targets = array();
        $found = array();
        $unavailable = array();
        foreach ($entries as $type => $entry) {
            $targets[$type] = $this->target($entry);
            foreach ($this->searchers as $searcher) {
                // Fuzzy and meaning cannot honour "phrase" and -word, so those queries keep keyword and exact matches
                if ($query->hasOperators() && in_array($searcher->kind(), array(FuzzySearcher::KIND, SemanticSearcher::KIND), true)) {
                    continue;
                }
                if (in_array($searcher->kind(), $unavailable, true) || !$this->mayMatch($targets[$type], $searcher)) {
                    continue;
                }
                try {
                    $signals = $searcher->rank($targets[$type], $query, $this->candidateDepth);
                } catch (SearcherUnavailableException $e) {
                    $this->logUnavailable($searcher->kind(), $e);
                    $unavailable[] = $searcher->kind();
                    continue;
                }
                $keyed = array();
                foreach ($signals as $id => $signal) {
                    $keyed[$type . ':' . $id] = $signal;
                }
                $found[] = array('kind' => $searcher->kind(), 'signals' => $keyed);
            }
        }

        $ranked = $this->ranking->rank($found, $request->limit(), $query->hasSeveralWords());

        return new SearchResults($this->results($ranked, $targets, $query->text()), $unavailable);
    }

    /**
     * @param string $kind
     * @param SearcherUnavailableException $e
     * @return void
     */
    private function logUnavailable($kind, SearcherUnavailableException $e)
    {
        global $logger;
        if ($logger) {
            $logger->error("Gaia.Search.SearchService: {$kind} matching is unavailable: " . $e->getMessage());
        }
    }

    /**
     * This function builds a type's target: its block, table, fields and the user's scope over it.
     *
     * @param array $entry The registry entry
     * @return SearchTarget
     */
    private function target(array $entry)
    {
        $meta = $this->metaManager->getModelMeta($entry['model']);
        $fields = isset($meta['fields']) ? $meta['fields'] : array();
        $scope = $this->scope->forModel($entry['model'], $fields);

        return new SearchTarget($entry, $meta['tableName'], $fields, $scope['sql'], $scope['bind']);
    }

    /**
     * Whether a searcher may run for a type: it has fields there and the user may read every one of them.
     *
     * @param SearchTarget $target
     * @param Searcher $searcher
     * @return bool
     */
    private function mayMatch(SearchTarget $target, Searcher $searcher)
    {
        $fields = $searcher->fields($target);
        foreach ($fields as $field) {
            if (!$this->acl->isFieldActionAllowed($target->model(), $field, 'get')) {
                return false;
            }
        }

        return $fields !== array();
    }

    /**
     * This function turns the ranked items into results with their display values.
     *
     * @param array $ranked Items from StrongestSignal, keys "type:id"
     * @param SearchTarget[] $targets Keyed by type
     * @param string $query
     * @return array
     */
    private function results(array $ranked, array $targets, $query)
    {
        $idsByType = array();
        foreach ($ranked as $item) {
            list($type, $id) = explode(':', $item['key'], 2);
            $idsByType[$type][] = $id;
        }

        $values = array();
        foreach ($idsByType as $type => $ids) {
            $target = $targets[$type];
            $values[$type] = $this->display->load($target, $ids, $query, $this->readableDisplay($target));
        }

        $results = array();
        foreach ($ranked as $item) {
            list($type, $id) = explode(':', $item['key'], 2);
            if (!isset($values[$type][$id])) {
                continue;
            }
            $results[] = array('type' => $type, 'id' => $id) + $values[$type][$id] + array(
                'score' => $item['score'],
                'matchedBy' => $item['matchedBy'],
            );
        }

        return $results;
    }

    /**
     * This function returns the display entries of a type whose fields the user may read.
     *
     * @param SearchTarget $target
     * @return array
     */
    private function readableDisplay(SearchTarget $target)
    {
        $display = array();
        foreach ($target->block('display') as $key => $field) {
            if ($field !== null && $this->acl->isFieldActionAllowed($target->model(), $field, 'get')) {
                $display[$key] = $field;
            }
        }

        return $display;
    }
}
