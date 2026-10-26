<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\FixtureExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the api-tester fixture extractor (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FixtureExtractorTest extends TestCase
{
    public function testExtractsProfilesWithTheDefaultFirstAndSortedValueKeys()
    {
        $fixtures = (new FixtureExtractor(__DIR__ . '/fixtures/repo'))->extract();

        $this->assertSame('tools/api-tester/fixtures/default.json', $fixtures['file']);
        $this->assertSame(['default', 'limited'], $fixtures['profiles']);
        $this->assertSame(['projectId', 'widgetId'], $fixtures['values']);
    }

    public function testLeavesCredentialsOut()
    {
        $json = json_encode((new FixtureExtractor(__DIR__ . '/fixtures/repo'))->extract());

        $this->assertStringNotContainsString('fixture-secret', $json);
        $this->assertStringNotContainsString('fixture-password', $json);
        $this->assertStringNotContainsString('@example.com', $json);
    }
}
