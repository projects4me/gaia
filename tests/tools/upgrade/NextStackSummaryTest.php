<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\Upgrade;

use PHPUnit\Framework\TestCase;
use Upgrade\NextStackFindings;
use Upgrade\NextStackSummary;

/**
 * Unit tests for the CI next-stack summary (agaia-4693).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NextStackSummaryTest extends TestCase
{
    public function testPhpunitFailuresListsFailuresAndErrors()
    {
        $xml = '<?xml version="1.0"?><testsuites><testsuite name="all">'
            . '<testcase class="A\\FooTest" name="testOk"/>'
            . '<testcase class="A\\FooTest" name="testBad"><failure type="x">Failed asserting that 1 is 2.' . "\n" . 'trace</failure></testcase>'
            . '<testcase class="A\\BarTest" name="testBoom"><error type="Error">Call to undefined method</error></testcase>'
            . '</testsuite></testsuites>';
        $f = (new NextStackFindings())->phpunitFailures($xml);
        $this->assertSame(2, $f['count']);
        $this->assertSame('A\\FooTest::testBad (failure): Failed asserting that 1 is 2.', $f['lines'][0]);
        $this->assertSame('A\\BarTest::testBoom (error): Call to undefined method', $f['lines'][1]);
    }

    public function testMissingOutputHasNoCount()
    {
        $findings = new NextStackFindings();
        $this->assertNull($findings->phpunitFailures(null)['count']);
        $this->assertNull($findings->apiTesterFailures('not json')['count']);
        $this->assertNull($findings->deprecations(null)['count']);
        $this->assertSame('a schema snapshot is missing', $findings->schemaDifferences('', false)['note']);
    }

    public function testDryRun()
    {
        $findings = new NextStackFindings();
        $planned = $findings->dryRunStatements("issues\tALTER TABLE \"issues\" ADD \"x\" text\nDry run: 1 statement(s) planned, none run.\n");
        $this->assertSame(1, $planned['count']);
        $this->assertSame('issues: ALTER TABLE "issues" ADD "x" text', $planned['lines'][0]);

        $this->assertSame(0, $findings->dryRunStatements("Dry run: nothing planned.\n")['count']);

        $failed = $findings->dryRunStatements("Migration dry run failed: boom\n");
        $this->assertNull($failed['count']);
        $this->assertSame('dry run failed: boom', $failed['note']);

        $this->assertNull($findings->dryRunStatements("PHP Fatal error: x\n")['count']);
    }

    public function testSchemaDifferencesSkipsDiffHeaders()
    {
        $diff = "--- current/schema.sql\n+++ next/schema.sql\n@@ -1,2 +1,2 @@\n CREATE TABLE a (\n-    x integer\n+    x bigint\n+\n";
        $f = (new NextStackFindings())->schemaDifferences($diff);
        $this->assertSame(['-    x integer', '+    x bigint'], $f['lines']);
        $this->assertSame(0, (new NextStackFindings())->schemaDifferences('')['count']);
    }

    public function testApiTesterFailures()
    {
        $report = json_encode(['summary' => [], 'results' => [
            ['id' => 'a', 'status' => 'passed'],
            ['id' => 'bulk-create', 'status' => 'failed', 'request' => ['method' => 'POST', 'url' => 'http://x/api/v1/bulk']],
        ]]);
        $f = (new NextStackFindings())->apiTesterFailures($report);
        $this->assertSame(['bulk-create (POST http://x/api/v1/bulk)'], $f['lines']);
    }

    public function testResponseDifferences()
    {
        $findings = new NextStackFindings();
        $output = "a: a.json\nb: b.json\nCases compared: 3\n\nDifferences per kind:\n  body           1\n\n"
            . "Differences per endpoint:\n  POST /bulk: body 1\n    [body] bulk-create: {\"data\":[]} -> \"\"\n\n1 difference(s).\n";
        $f = $findings->responseDifferences($output);
        $this->assertSame(1, $f['count']);
        $this->assertSame(['POST /bulk: body 1', '[body] bulk-create: {"data":[]} -> ""'], $f['lines']);

        $this->assertSame(0, $findings->responseDifferences("Cases compared: 3\nNo differences.\n")['count']);
        $this->assertNull($findings->responseDifferences("Not a readable api-tester report: x\n")['count']);
    }

    public function testDeprecations()
    {
        $md = "# PHP deprecations and warnings\n\n7 entries: 5 in Gaia code, 2 in vendor.\n\n## Gaia code\n\n### Deprecated x5: Param\n";
        $f = (new NextStackFindings())->deprecations($md);
        $this->assertSame(7, $f['count']);
        $this->assertSame(['Deprecated x5: Param'], $f['lines']);
        $this->assertSame('5 in Gaia code, 2 in vendor.', $f['note']);
    }

    public function testRenderTableAndDetails()
    {
        $md = (new NextStackSummary())
            ->add('PHPUnit failures', 'upgrade-next/phpunit-junit.xml', ['count' => 1, 'lines' => ['A::b (failure)'], 'note' => ''])
            ->add('Dry-run statements', 'upgrade-next/dry-run.txt', ['count' => null, 'lines' => [], 'note' => 'dry run failed: a|b'])
            ->render();
        $this->assertStringContainsString('| PHPUnit failures | 1 | `upgrade-next/phpunit-junit.xml` |', $md);
        $this->assertStringContainsString('| Dry-run statements | n/a (dry run failed: a\|b) |', $md);
        $this->assertStringContainsString("<details><summary>PHPUnit failures</summary>\n\n```\nA::b (failure)\n```", $md);
        $this->assertStringNotContainsString('<summary>Dry-run statements', $md);
    }

    public function testRenderCapsLongLists()
    {
        $lines = array_map(function ($i) {
            return "line {$i}";
        }, range(1, NextStackSummary::MAX_LINES + 3));
        $md = (new NextStackSummary())->add('X', 'x.txt', ['count' => count($lines), 'lines' => $lines, 'note' => ''])->render();
        $this->assertStringContainsString('... 3 more in x.txt', $md);
        $this->assertStringNotContainsString('line ' . (NextStackSummary::MAX_LINES + 1) . "\n", $md);
    }
}
