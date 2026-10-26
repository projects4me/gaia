<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph;

use GaiaDev\Extractor\AclExtractor;
use GaiaDev\Extractor\AssertionExtractor;
use GaiaDev\Extractor\BehaviourExtractor;
use GaiaDev\Extractor\CatalogExtractor;
use GaiaDev\Extractor\ComponentExtractor;
use GaiaDev\Extractor\ControllerExtractor;
use GaiaDev\Extractor\FixtureExtractor;
use GaiaDev\Extractor\MetadataModelExtractor;
use GaiaDev\Extractor\ModelListExtractor;
use GaiaDev\Extractor\ProfileExtractor;
use GaiaDev\Extractor\RouteExtractor;
use GaiaDev\Graph\Mapper\AclMapper;
use GaiaDev\Graph\Mapper\AssertionMapper;
use GaiaDev\Graph\Mapper\BehaviourMapper;
use GaiaDev\Graph\Mapper\CatalogMapper;
use GaiaDev\Graph\Mapper\ControllerMapper;
use GaiaDev\Graph\Mapper\FixtureMapper;
use GaiaDev\Graph\Mapper\ModelMapper;
use GaiaDev\Graph\Mapper\ProfileMapper;
use GaiaDev\Graph\Mapper\RouteMapper;

/**
 * Builds the graph of a Gaia checkout: runs each extractor over the repository
 * and hands its records to the matching mapper, in dependency order: models,
 * routes, then everything that links to them (code, fixtures, catalogs, profiles,
 * ACL rules).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GraphBuilder
{
    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = $root;
    }

    /**
     * @return Graph
     */
    public function build(): Graph
    {
        $graph = new Graph();

        (new ModelMapper())->map(
            $graph,
            (new MetadataModelExtractor($this->root))->extract(),
            (new ModelListExtractor($this->root))->extract()
        );
        (new RouteMapper())->map($graph, (new RouteExtractor($this->root))->extract());
        (new BehaviourMapper())->map($graph, (new BehaviourExtractor($this->root))->extract());
        (new ControllerMapper())->map(
            $graph,
            (new ControllerExtractor($this->root))->extract(),
            (new ComponentExtractor($this->root))->extract()
        );
        (new FixtureMapper())->map($graph, (new FixtureExtractor($this->root))->extract());

        $catalogs = (new CatalogExtractor($this->root))->extract();
        (new CatalogMapper())->map($graph, $catalogs);
        (new AssertionMapper())->map($graph, (new AssertionExtractor($this->root))->extract(), $catalogs);

        (new ProfileMapper())->map($graph, (new ProfileExtractor($this->root))->extract());
        (new AclMapper())->map($graph, (new AclExtractor($this->root))->extract());

        return $graph;
    }
}
