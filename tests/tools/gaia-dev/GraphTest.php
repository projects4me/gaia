<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\GraphJson;
use GaiaDev\Graph\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the graph model, its schema checks and its JSON form (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GraphTest extends TestCase
{
    public function testAddingAnExistingNodeMergesItsAttributes()
    {
        $graph = new Graph();
        $graph->addNode(Schema::MODEL, 'Issue', ['registered' => true]);
        $id = $graph->addNode(Schema::MODEL, 'Issue', ['hasMetadata' => true]);

        $this->assertSame('model:Issue', $id);
        $this->assertSame(
            ['registered' => true, 'hasMetadata' => true],
            $graph->toArray()['nodes'][0]['attributes']
        );
    }

    public function testRejectsUnknownNodeTypes()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Graph())->addNode('table', 'issues');
    }

    public function testRejectsEdgesBetweenTheWrongNodeTypes()
    {
        $graph = new Graph();
        $model = $graph->addNode(Schema::MODEL, 'Issue');
        $behaviour = $graph->addNode(Schema::BEHAVIOUR, 'auditBehavior');

        $this->expectException(InvalidArgumentException::class);

        $graph->addEdge($model, 'has_field', $behaviour);
    }

    public function testRejectsEdgesToMissingNodes()
    {
        $graph = new Graph();
        $model = $graph->addNode(Schema::MODEL, 'Issue');

        $this->expectException(InvalidArgumentException::class);

        $graph->addEdge($model, 'has_field', 'field:Issue.subject');
    }

    public function testExportIsSortedAndDeduplicated()
    {
        $graph = new Graph();
        $issue = $graph->addNode(Schema::MODEL, 'Issue');
        $subject = $graph->addNode(Schema::FIELD, 'Issue.subject');
        $id = $graph->addNode(Schema::FIELD, 'Issue.id');
        $graph->addEdge($issue, 'has_field', $subject);
        $graph->addEdge($issue, 'has_field', $id);
        $graph->addEdge($issue, 'has_field', $subject);

        $export = $graph->toArray();

        $this->assertSame(['field:Issue.id', 'field:Issue.subject', 'model:Issue'], array_column($export['nodes'], 'id'));
        $this->assertSame(['field:Issue.id', 'field:Issue.subject'], array_column($export['edges'], 'to'));
    }

    public function testJsonHasTheSchemaVersionTwoSpaceIndentAndObjectAttributes()
    {
        $graph = new Graph();
        $graph->addNode(Schema::BEHAVIOUR, 'auditBehavior');

        $json = GraphJson::encode($graph);
        $decoded = json_decode($json);

        $this->assertSame(Schema::VERSION, $decoded->schemaVersion);
        $this->assertInstanceOf(\stdClass::class, $decoded->nodes[0]->attributes);
        $this->assertStringContainsString("\n  \"schemaVersion\": 1,", $json);
        $this->assertStringEndsWith("}\n", $json);
        $this->assertStringNotContainsString("\r", $json);
    }

    public function testJsonRejectsInvalidUtf8InsteadOfWritingAnEmptyGraph()
    {
        $graph = new Graph();
        $graph->addNode(Schema::BEHAVIOUR, 'auditBehavior', ['label' => "bad \xB1 byte"]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot encode the graph: Malformed UTF-8');

        GraphJson::encode($graph);
    }

    public function testEverySchemaEdgeConnectsKnownNodeTypes()
    {
        foreach (Schema::EDGES as $type => $ends) {
            foreach ($ends as $nodeType) {
                $this->assertContains($nodeType, Schema::nodeTypes(), "edge {$type}");
            }
        }
    }
}
