<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Check\ApiCasesCheck;
use GaiaDev\Cli;
use GaiaDev\Query\GraphReader;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev check api-cases` (agaia-4133).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CheckApiCasesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /**
     * @param array[] $nodes [id, attributes]
     * @param array[] $edges [from, type, to]
     * @return GraphReader
     */
    private function graph(array $nodes, array $edges = []): GraphReader
    {
        $document = ['nodes' => [], 'edges' => []];
        foreach ($nodes as list($id, $attributes)) {
            list($type, $key) = explode(':', $id, 2);
            $document['nodes'][] = ['id' => $id, 'type' => $type, 'key' => $key, 'attributes' => $attributes];
        }
        foreach ($edges as list($from, $type, $to)) {
            $document['edges'][] = ['from' => $from, 'type' => $type, 'to' => $to];
        }

        return new GraphReader($document);
    }

    private function caseNode(string $key, string $catalog, string $resource, array $placeholders = [], array $stores = []): array
    {
        return ['case:' . $key, [
            'catalog' => $catalog, 'enabled' => true, 'method' => 'GET', 'path' => '/api/v1/' . $resource, 'resource' => $resource,
            'placeholders' => $placeholders, 'stores' => $stores,
        ]];
    }

    public function testReportsARestrictedResourceWithoutAclCases()
    {
        $graph = $this->graph(
            [
                ['profile:aclX', []],
                ['permission:widget.get', ['kind' => 'action']],
                ['permission:other.get', ['kind' => 'action']],
                $this->caseNode('client/widget', 'client', 'widget'),
                $this->caseNode('acl/other', 'acl', 'other'),
            ],
            [['profile:aclX', 'denies', 'permission:widget.get'], ['profile:aclX', 'grants_members', 'permission:other.get']]
        );

        $this->assertSame(
            ['acl: aclX is restricted on widget.get but no acl case covers resource widget'],
            (new ApiCasesCheck($graph))->problems()
        );
        $this->assertSame([], (new ApiCasesCheck($graph))->problems('other'));
    }

    public function testADisabledAclCaseDoesNotCoverItsResource()
    {
        $disabled = $this->caseNode('acl/widget', 'acl', 'widget');
        $disabled[1]['enabled'] = false;
        $graph = $this->graph(
            [['profile:aclX', []], ['permission:widget.get', ['kind' => 'action']], $disabled],
            [['profile:aclX', 'denies', 'permission:widget.get']]
        );

        $this->assertSame(
            ['acl: aclX is restricted on widget.get but no acl case covers resource widget'],
            (new ApiCasesCheck($graph))->problems()
        );
    }

    public function testReportsPlaceholdersNothingResolvesAndUnknownAssertions()
    {
        $graph = $this->graph(
            [
                ['fixture:projectId', []],
                $this->caseNode('client/a', 'client', 'widget', ['projectId', 'runtime.widgetId', 'runId', 'missingId']),
                $this->caseNode('client/b', 'client', 'widget', [], ['runtime.widgetId']),
                $this->caseNode('client/c', 'client', 'widget', ['runtime.widgetId']),
                ['assertion:status', ['source' => 'Asserter.php']],
                ['assertion:statuz', []],
            ],
            [['case:client/a', 'depends_on', 'case:client/b'], ['case:client/a', 'asserts', 'assertion:status'], ['case:client/a', 'asserts', 'assertion:statuz']]
        );

        $this->assertSame([
            'placeholder: client/a uses {missingId}, which is no fixture, no id stored by an earlier case or runner value',
            'placeholder: client/c uses {runtime.widgetId}, which is no fixture, no id stored by an earlier case or runner value',
            "assertion: client/a asserts 'statuz', which the Asserter does not implement",
        ], (new ApiCasesCheck($graph))->problems());
    }

    public function testTheCommittedCatalogsAreConsistent()
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        $code = Cli::main(['gaia-dev', 'check', 'api-cases'], self::ROOT, $stdout, $stderr);

        rewind($stderr);
        $this->assertSame(0, $code, stream_get_contents($stderr));
        rewind($stdout);
        $this->assertSame("api-cases: ok\n", stream_get_contents($stdout));
    }

    public function testCheckOneEndpointSkipsTheBackendRegeneration()
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        $code = Cli::main(['gaia-dev', 'check', 'api-cases', 'issue'], self::ROOT, $stdout, $stderr);

        $this->assertSame(0, $code);
    }
}
