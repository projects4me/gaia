<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Upgrade;

/**
 * Reads the outputs of the CI next-stack job (and the compare step) into findings: a count and the
 * lines that explain it. Each method takes the raw text of one output, so it can be tested without files.
 *
 * A finding is ['count' => int|null, 'lines' => string[], 'note' => string]; count null means the output
 * could not be read (the step that writes it failed or did not run), and note says why.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Upgrade
 * @category Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NextStackFindings
{
    /**
     * Failed and errored test cases in a PHPUnit JUnit report.
     *
     * @param string|null $junitXml
     * @return array finding
     */
    public function phpunitFailures($junitXml)
    {
        if ($junitXml === null || trim($junitXml) === '') {
            return self::missing('no JUnit report');
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($junitXml);
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            return self::missing('JUnit report is not valid XML');
        }
        $lines = [];
        foreach ($xml->xpath('//testcase[failure or error]') as $case) {
            $kind = isset($case->error) ? 'error' : 'failure';
            $node = $kind === 'error' ? $case->error : $case->failure;
            $message = trim(strtok((string) $node, "\n"));
            $lines[] = "{$case['class']}::{$case['name']} ({$kind})" . ($message === '' ? '' : ": {$message}");
        }
        return self::found($lines);
    }

    /**
     * Statements planned by `migration migrate --dry-run` (lines "<table>\t<sql>").
     *
     * @param string|null $output
     * @return array finding
     */
    public function dryRunStatements($output)
    {
        if ($output === null || trim($output) === '') {
            return self::missing('no dry-run output');
        }
        if (preg_match('/^Migration dry run failed: (.*)$/m', $output, $m)) {
            return self::missing('dry run failed: ' . $m[1]);
        }
        if (!preg_match('/^Dry run: (nothing planned|(\d+) statement)/m', $output)) {
            return self::missing('dry run did not finish: ' . self::lastLine($output));
        }
        $lines = [];
        foreach (preg_split('/\R/', $output) as $line) {
            if (strpos($line, "\t") !== false) {
                $lines[] = str_replace("\t", ': ', $line);
            }
        }
        return self::found($lines);
    }

    /**
     * Changed lines in a unified diff of two schema snapshots.
     *
     * @param string|null $diff empty when the snapshots are identical
     * @param bool $snapshotsPresent false when a snapshot was not produced
     * @return array finding
     */
    public function schemaDifferences($diff, $snapshotsPresent = true)
    {
        if (!$snapshotsPresent) {
            return self::missing('a schema snapshot is missing');
        }
        $lines = [];
        foreach (preg_split('/\R/', (string) $diff) as $line) {
            if (preg_match('/^[+-](?![+-]{2} )/', $line) && trim(substr($line, 1)) !== '') {
                $lines[] = $line;
            }
        }
        return self::found($lines);
    }

    /**
     * Failed cases in an api-tester report.
     *
     * @param string|null $reportJson
     * @return array finding
     */
    public function apiTesterFailures($reportJson)
    {
        $report = $reportJson === null ? null : json_decode($reportJson, true);
        if (!is_array($report) || !isset($report['results']) || !is_array($report['results'])) {
            return self::missing('no api-tester report');
        }
        $lines = [];
        foreach ($report['results'] as $result) {
            if (($result['status'] ?? '') !== 'failed') {
                continue;
            }
            $request = $result['request'] ?? [];
            $where = trim(($request['method'] ?? '') . ' ' . ($request['url'] ?? ''));
            $lines[] = ($result['id'] ?? '?') . ($where === '' ? '' : " ({$where})");
        }
        return self::found($lines);
    }

    /**
     * Response differences from `compare-reports.php --details`.
     *
     * @param string|null $output
     * @return array finding; lines are the per-endpoint summary and the listed differences
     */
    public function responseDifferences($output)
    {
        if ($output === null || trim($output) === '') {
            return self::missing('no comparison output');
        }
        if (strpos($output, 'No differences.') !== false) {
            return self::found([]);
        }
        if (!preg_match('/^(\d+) difference\(s\)/m', $output, $m)) {
            return self::missing('comparison did not run: ' . self::lastLine($output));
        }
        $lines = [];
        $inEndpoints = false;
        foreach (preg_split('/\R/', $output) as $line) {
            if (strpos($line, 'Differences per endpoint:') === 0) {
                $inEndpoints = true;
                continue;
            }
            if ($inEndpoints && trim($line) !== '' && strpos($line, '  ') === 0) {
                $lines[] = trim($line);
            }
        }
        return ['count' => (int) $m[1], 'lines' => $lines, 'note' => ''];
    }

    /**
     * Entry count from the markdown of deprecations-report.php.
     *
     * @param string|null $markdown
     * @return array finding; lines are the pattern headings
     */
    public function deprecations($markdown)
    {
        if ($markdown === null || !preg_match('/^(\d+) entries: (.*)$/m', $markdown, $m)) {
            return self::missing('no deprecations report');
        }
        preg_match_all('/^### (.*)$/m', $markdown, $headings);
        return ['count' => (int) $m[1], 'lines' => $headings[1], 'note' => $m[2]];
    }

    /**
     * @param string[] $lines
     * @return array
     */
    private static function found(array $lines)
    {
        return ['count' => count($lines), 'lines' => $lines, 'note' => ''];
    }

    /**
     * @param string $why
     * @return array
     */
    private static function missing($why)
    {
        return ['count' => null, 'lines' => [], 'note' => $why];
    }

    /**
     * @param string $text
     * @return string
     */
    private static function lastLine($text)
    {
        $lines = preg_split('/\R/', trim($text));
        return end($lines);
    }
}
