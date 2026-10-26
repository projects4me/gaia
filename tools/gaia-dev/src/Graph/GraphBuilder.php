<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph;

use GaiaDev\Extractor\CatalogExtractor;
use GaiaDev\Extractor\FixtureExtractor;
use GaiaDev\Extractor\MetadataModelExtractor;
use GaiaDev\Extractor\ModelListExtractor;
use GaiaDev\Extractor\RouteExtractor;
use GaiaDev\Graph\Mapper\CatalogMapper;
use GaiaDev\Graph\Mapper\FixtureMapper;
use GaiaDev\Graph\Mapper\ModelMapper;
use GaiaDev\Graph\Mapper\RouteMapper;

/**
 * Builds the graph of a Gaia checkout: runs each extractor over the repository
 * and hands its records to the matching mapper, in dependency order.
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
        (new FixtureMapper())->map($graph, (new FixtureExtractor($this->root))->extract());
        (new CatalogMapper())->map($graph, (new CatalogExtractor($this->root))->extract());

        return $graph;
    }
}
