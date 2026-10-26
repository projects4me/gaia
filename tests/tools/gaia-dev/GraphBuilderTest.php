<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\CatalogExtractor;
use GaiaDev\Extractor\MetadataModelExtractor;
use GaiaDev\Extractor\ModelListExtractor;
use GaiaDev\Extractor\RouteExtractor;
use GaiaDev\Graph\GraphBuilder;
use GaiaDev\Graph\Mapper\RouteMapper;
use PHPUnit\Framework\TestCase;

/**
 * Tests the graph built from the fixture repository (node and edge mapping)
 * and from this repository (every model, route and case is present) (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GraphBuilderTest extends TestCase
{
    /**
     * @var array|null graph export of the fixture repository
     */
    private static $fixtureGraph;

    /**
     * @return array
     */
    private function fixtureGraph(): array
    {
        if (self::$fixtureGraph === null) {
            self::$fixtureGraph = (new GraphBuilder(__DIR__ . '/fixtures/repo'))->build()->toArray();
        }

        return self::$fixtureGraph;
    }

    /**
     * @param string $id
     * @return array
     */
    private function node(string $id): array
    {
        $nodes = array_column($this->fixtureGraph()['nodes'], null, 'id');
        $this->assertArrayHasKey($id, $nodes);

        return $nodes[$id];
    }

    /**
     * @param string $from
     * @param string $type
     * @return string[] target node ids
     */
    private function targets(string $from, string $type): array
    {
        $targets = [];
        foreach ($this->fixtureGraph()['edges'] as $edge) {
            if ($edge['from'] === $from && $edge['type'] === $type) {
                $targets[] = $edge['to'];
            }
        }

        return $targets;
    }

    public function testModelsCarryRegistrationAndMetadataFlags()
    {
        $this->assertTrue($this->node('model:Widget')['attributes']['registered']);
        $this->assertTrue($this->node('model:Widget')['attributes']['hasMetadata']);
        $this->assertFalse($this->node('model:Tag')['attributes']['registered']);
        $this->assertSame(['registered' => true, 'hasMetadata' => false], $this->node('model:Ghost')['attributes']);
        $this->assertSame(['registered' => false, 'hasMetadata' => false], $this->node('model:User')['attributes']);
    }

    public function testModelMembers()
    {
        $this->assertSame(
            ['field:Widget.id', 'field:Widget.ownerId', 'field:Widget.ownerName', 'field:Widget.secret'],
            $this->targets('model:Widget', 'has_field')
        );
        $this->assertSame(['field:Widget.ownerId'], $this->targets('field:Widget.ownerName', 'linked_to'));
        $this->assertFalse($this->node('field:Widget.secret')['attributes']['acl']);
        $this->assertSame(
            ['behaviour:auditBehavior', 'behaviour:softDeleteBehavior'],
            $this->targets('model:Widget', 'uses_behaviour')
        );
    }

    public function testRelationshipsTargetModelsAndManyToManyGoesVia()
    {
        $this->assertSame(['model:User'], $this->targets('relationship:Widget.owner', 'targets'));
        $this->assertSame(['model:Tag'], $this->targets('relationship:Widget.tags', 'targets'));
        $this->assertSame(['model:Widgettag'], $this->targets('relationship:Widget.tags', 'via'));
        $this->assertSame('hasManyToMany', $this->node('relationship:Widget.tags')['attributes']['kind']);
    }

    public function testRoutesAreHandledByControllerActions()
    {
        $this->assertSame(['action:widget.get'], $this->targets('route:GET /api/v1/widget/{id}', 'handled_by'));
        $this->assertSame(['action:export.get'], $this->targets('route:ANY /export/{format}', 'handled_by'));
        $this->assertSame(['action:error.not_found'], $this->targets('route:system:not_found', 'handled_by'));
        $this->assertContains('action:widget.options', $this->targets('controller:widget', 'has_action'));
        $this->assertSame(['model:Widget'], $this->targets('controller:widget', 'serves'));
        $this->assertSame([], $this->targets('controller:export', 'serves'));
    }

    public function testCasesLinkToRoutesProfilesAssertionsAndFixtures()
    {
        $case = 'case:backend/post-widget';

        $this->assertSame(['controller:widget'], $this->targets($case, 'covers'));
        $this->assertSame(['route:POST /api/v1/widget'], $this->targets($case, 'exercises'));
        $this->assertSame(['profile:default'], $this->targets($case, 'runs_as'));
        $this->assertSame(['assertion:shape', 'assertion:status'], $this->targets($case, 'asserts'));
        $this->assertSame(['fixture:projectId'], $this->targets($case, 'uses_fixture'));
        $this->assertSame('backend', $this->node($case)['attributes']['catalog']);
    }

    public function testCaseReadingAStoredValueDependsOnTheCaseThatStoresIt()
    {
        $this->assertSame(
            ['case:backend/post-widget'],
            $this->targets('case:backend/get-created-widget', 'depends_on')
        );
        $this->assertSame(['route:GET /api/v1/widget/{id}'], $this->targets('case:backend/get-created-widget', 'exercises'));
    }

    public function testProfilesFromFixturesAndCases()
    {
        $this->assertSame(['profile:limited'], $this->targets('case:backend/list-widgets-limited', 'runs_as'));
        $this->assertSame(['profile:anonymous'], $this->targets('case:backend/export-anonymous', 'runs_as'));
        $this->assertSame(['route:ANY /export/{format}'], $this->targets('case:backend/export-anonymous', 'exercises'));
    }

    public function testUnroutedCaseHasNoRouteButStillUsesItsFixture()
    {
        $this->assertSame([], $this->targets('case:backend/delete-widget-not-routed', 'exercises'));
        $this->assertSame(['fixture:widgetId'], $this->targets('case:backend/delete-widget-not-routed', 'uses_fixture'));
    }

    public function testEveryRepositoryModelRouteAndCaseIsInTheGraph()
    {
        $root = dirname(__DIR__, 3);
        $nodes = array_column((new GraphBuilder($root))->build()->toArray()['nodes'], null, 'id');

        $expected = [];
        foreach ((new MetadataModelExtractor($root))->extract() as $model) {
            $expected[] = 'model:' . $model['name'];
        }
        foreach ((new ModelListExtractor($root))->extract() as $name) {
            $expected[] = 'model:' . $name;
        }
        foreach ((new RouteExtractor($root))->extract() as $route) {
            $expected[] = 'route:' . RouteMapper::routeKey($route);
        }
        foreach ((new CatalogExtractor($root))->extract() as $catalog) {
            foreach ($catalog['cases'] as $case) {
                $expected[] = 'case:' . $catalog['catalog'] . '/' . $case['id'];
            }
        }

        $this->assertSame([], array_values(array_diff($expected, array_keys($nodes))));
    }
}
