<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\AssertionExtractor;
use GaiaDev\Extractor\CatalogExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the assertion vocabulary extractor, and the check that every
 * assertion the catalogs use is known to it (agaia-4129).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AssertionExtractorTest extends TestCase
{
    /**
     * @param string $root
     * @return array[] records by name
     */
    private function records(string $root): array
    {
        return array_column((new AssertionExtractor($root))->extract(), null, 'name');
    }

    public function testExtractsTheAsserterKeysAndRunnerKeys()
    {
        $this->assertSame(
            [
                'attributesEqual', 'errorEqual', 'hermes.expect', 'mail.fromEmail', 'mail.fromName',
                'mail.subject', 'mail.to', 'shape', 'status',
            ],
            array_keys($this->records(__DIR__ . '/fixtures/repo'))
        );
    }

    public function testRunOptionsAreNotAssertions()
    {
        $records = $this->records(__DIR__ . '/fixtures/repo');

        $this->assertArrayNotHasKey('hermes.timeoutMs', $records);
        $this->assertArrayNotHasKey('hermes.requireUp', $records);
        $this->assertArrayNotHasKey('mail.timeoutMs', $records);
    }

    public function testRecordsWhereEachIsCheckedAndWhatItsFailureSays()
    {
        $records = $this->records(__DIR__ . '/fixtures/repo');

        $this->assertSame('tools/api-tester/src/Asserter.php', $records['status']['source']);
        $this->assertSame(12, $records['status']['line']);
        $this->assertSame(['status: expected %s, got %s'], $records['status']['messages']);
        $this->assertSame(["errorEqual: expected '…'"], $records['errorEqual']['messages']);
        $this->assertSame('tools/api-tester/src/Runner.php', $records['mail.subject']['source']);
        $this->assertSame(['json', 'jsonapi.resource'], $records['shape']['values']);
    }

    public function testEveryAssertionTheCatalogsUseIsKnown()
    {
        $root = dirname(__DIR__, 3);
        $known = array_keys($this->records($root));

        $unknown = [];
        foreach ((new CatalogExtractor($root))->extract() as $catalog) {
            foreach ($catalog['cases'] as $case) {
                foreach (array_diff($case['assertions'], $known) as $name) {
                    $unknown[$name][] = $catalog['catalog'] . '/' . $case['id'];
                }
            }
        }

        $this->assertSame(
            [],
            $unknown,
            'Assertion used in a catalog but not checked by the api-tester (or not extracted): add it to Asserter/Runner'
        );
    }
}
