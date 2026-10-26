<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\RouteMatcher;
use GaiaDev\Graph\Schema;

/**
 * Maps api-tester catalog records onto case and assertion nodes, and links each
 * case to the controller it covers, the route it exercises, the profile it runs
 * as, the fixtures it reads and the earlier cases whose stored values it reads.
 *
 * Run it after RouteMapper and FixtureMapper.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CatalogMapper
{
    /**
     * @param Graph $graph
     * @param array[] $catalogs CatalogExtractor records
     */
    public function map(Graph $graph, array $catalogs)
    {
        $matcher = new RouteMatcher();
        foreach ($graph->nodesOfType(Schema::ROUTE) as $route) {
            $matcher->add($route['key'], $route['attributes']['method'], $route['attributes']['path']);
        }

        foreach ($catalogs as $catalog) {
            $this->mapCatalog($graph, $matcher, $catalog);
        }
    }

    /**
     * Stored runtime values are scoped to one catalog run, so a case depends
     * on the first earlier case in the same catalog that stores what it reads.
     *
     * @param Graph $graph
     * @param RouteMatcher $matcher
     * @param array $catalog
     */
    private function mapCatalog(Graph $graph, RouteMatcher $matcher, array $catalog)
    {
        $storedBy = [];

        foreach ($catalog['cases'] as $case) {
            $caseId = $graph->addNode(Schema::TEST_CASE, $catalog['catalog'] . '/' . $case['id'], [
                'catalog' => $catalog['catalog'],
                'file' => $catalog['file'],
                'method' => $case['method'],
                'path' => $case['path'],
                'resource' => $case['resource'],
                'enabled' => $case['enabled'],
                'source' => $case['source'],
                'placeholders' => $case['placeholders'],
                'stores' => $case['stores'],
            ]);

            $this->linkTargets($graph, $matcher, $caseId, $case);

            foreach ($case['placeholders'] as $key) {
                if (isset($storedBy[$key])) {
                    $graph->addEdge($caseId, 'depends_on', $storedBy[$key]);
                } elseif ($graph->hasNode(Schema::nodeId(Schema::FIXTURE, $key))) {
                    $graph->addEdge($caseId, 'uses_fixture', Schema::nodeId(Schema::FIXTURE, $key));
                }
            }

            foreach ($case['stores'] as $key) {
                if (!isset($storedBy[$key])) {
                    $storedBy[$key] = $caseId;
                }
            }
        }
    }

    /**
     * @param Graph $graph
     * @param RouteMatcher $matcher
     * @param string $caseId
     * @param array $case
     */
    private function linkTargets(Graph $graph, RouteMatcher $matcher, string $caseId, array $case)
    {
        $controllerId = Schema::nodeId(Schema::CONTROLLER, (string) $case['resource']);
        if ($graph->hasNode($controllerId)) {
            $graph->addEdge($caseId, 'covers', $controllerId);
        }

        $routeKey = $matcher->match($case['method'], $case['path']);
        if ($routeKey !== null) {
            $graph->addEdge($caseId, 'exercises', Schema::nodeId(Schema::ROUTE, $routeKey));
        }

        $graph->addEdge($caseId, 'runs_as', $graph->addNode(Schema::PROFILE, $case['profile']));

        foreach ($case['assertions'] as $assertion) {
            $graph->addEdge($caseId, 'asserts', $graph->addNode(Schema::ASSERTION, $assertion));
        }
    }
}
