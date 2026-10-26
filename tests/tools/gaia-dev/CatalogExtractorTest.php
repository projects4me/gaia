<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\CatalogExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the api-tester catalog extractor (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CatalogExtractorTest extends TestCase
{
    /**
     * @return array the fixture backend catalog record
     */
    private function catalog(): array
    {
        $catalogs = (new CatalogExtractor(__DIR__ . '/fixtures/repo'))->extract();
        $this->assertCount(1, $catalogs);

        return $catalogs[0];
    }

    /**
     * @return array[] cases keyed by id
     */
    private function cases(): array
    {
        return array_column($this->catalog()['cases'], null, 'id');
    }

    public function testCatalogRecordListsItsEndpoints()
    {
        $catalog = $this->catalog();

        $this->assertSame('backend', $catalog['catalog']);
        $this->assertSame('tools/api-tester/apis/backend.json', $catalog['file']);
        $this->assertSame([
            'DELETE /api/v1/widget/{widgetId}',
            'GET /api/v1/widget/{runtime.widgetId}',
            'GET /api/v1/widget?limit=5',
            'GET /export/csv',
            'POST /api/v1/widget',
        ], $catalog['endpoints']);
    }

    public function testCaseProfiles()
    {
        $cases = $this->cases();

        $this->assertSame('default', $cases['post-widget']['profile']);
        $this->assertSame('limited', $cases['list-widgets-limited']['profile']);
        $this->assertSame('anonymous', $cases['export-anonymous']['profile']);
    }

    public function testCaseAssertionsSkipHermesRunOptions()
    {
        $cases = $this->cases();

        $this->assertSame(['shape', 'status'], $cases['post-widget']['assertions']);
        $this->assertSame(['errorEqual', 'hermes.expect', 'status'], $cases['list-widgets-limited']['assertions']);
    }

    public function testCasePlaceholdersAndStoredValues()
    {
        $cases = $this->cases();

        $this->assertSame(['projectId', 'runId'], $cases['post-widget']['placeholders']);
        $this->assertSame(['runtime.widgetId'], $cases['post-widget']['stores']);
        $this->assertSame(['runtime.widgetId'], $cases['get-created-widget']['placeholders']);
        $this->assertSame([], $cases['get-created-widget']['stores']);
    }

    public function testMethodIsUppercasedAndEnabledDefaultsToTrue()
    {
        $cases = $this->cases();

        $this->assertSame('GET', $cases['list-widgets-limited']['method']);
        $this->assertTrue($cases['post-widget']['enabled']);
        $this->assertFalse($cases['export-anonymous']['enabled']);
        $this->assertSame('backend-metadata', $cases['post-widget']['source']);
    }

    public function testReadsEveryRepositoryCatalogCase()
    {
        $root = dirname(__DIR__, 3);
        $expected = 0;
        foreach (glob($root . '/tools/api-tester/apis/*.json') as $file) {
            $expected += count(json_decode(file_get_contents($file), true)['apis']);
        }

        $catalogs = (new CatalogExtractor($root))->extract();

        $this->assertSame(['acl', 'backend', 'client', 'live', 'mail'], array_column($catalogs, 'catalog'));
        $this->assertSame($expected, array_sum(array_map(function ($catalog) {
            return count($catalog['cases']);
        }, $catalogs)));
    }
}
