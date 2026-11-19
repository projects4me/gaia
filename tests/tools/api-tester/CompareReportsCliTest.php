<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\ApiTester;

use PHPUnit\Framework\TestCase;

/**
 * Runs tools/api-tester/bin/compare-reports.php on two report files and checks
 * its exit codes and summary (agaia-4685).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CompareReportsCliTest extends TestCase
{
    /** @var string[] temp files to remove */
    private $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /**
     * @param mixed $body
     * @return string path of a one-case report
     */
    private function reportFile($body)
    {
        $file = tempnam(sys_get_temp_dir(), 'api-tester-report');
        $this->files[] = $file;
        file_put_contents($file, json_encode(['runId' => 'abcd1234', 'results' => [[
            'id' => 'bulk-run',
            'status' => 'passed',
            'endpoint' => 'POST /api/v1/bulk',
            'response' => [
                'status' => 200,
                'contentType' => 'application/json',
                'bodyFormat' => is_string($body) ? 'text' : 'json',
                'body' => $body,
            ],
        ]]]));
        return $file;
    }

    /**
     * @param string[] $args
     * @return array [exit code, output]
     */
    private function runScript(array $args)
    {
        $script = dirname(__DIR__, 3) . '/tools/api-tester/bin/compare-reports.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }
        exec($command . ' 2>&1', $output, $code);
        return [$code, implode("\n", $output)];
    }

    public function testMatchingReportsExitZero()
    {
        list($code, $output) = $this->runScript([$this->reportFile(['results' => []]), $this->reportFile(['results' => []])]);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('No differences.', $output);
    }

    public function testDifferencesExitOneWithSummaryAndDetails()
    {
        list($code, $output) = $this->runScript([$this->reportFile(['results' => []]), $this->reportFile(''), '--details']);

        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('POST /api/v1/bulk: empty body 1', $output);
        $this->assertStringContainsString('[empty body] bulk-run $: non-empty -> empty', $output);
    }

    public function testBadArgumentsExitTwo()
    {
        list($code) = $this->runScript([$this->reportFile([])]);
        $this->assertSame(2, $code);

        list($code) = $this->runScript([$this->reportFile([]), '/no/such/report.json']);
        $this->assertSame(2, $code);
    }
}
