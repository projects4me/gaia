#!/usr/bin/env php
<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Compare two api-tester reports recorded with --record-bodies.
 *
 *   php tools/api-tester/bin/compare-reports.php a.json b.json [--details]
 *
 * Exit codes: 0 no differences, 1 differences, 2 usage or unreadable report.
 * The comparison itself is ApiTester\ReportComparer; this script only parses
 * arguments and prints.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package ApiTester
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "compare-reports must be run from CLI\n");
    exit(2);
}

define('API_TESTER_ROOT', realpath(dirname(__DIR__)));

spl_autoload_register(function ($class) {
    $prefix = 'ApiTester\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = API_TESTER_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$args = array_slice($argv, 1);
$details = in_array('--details', $args, true);
$files = array_values(array_filter($args, function ($arg) {
    return strpos($arg, '--') !== 0;
}));

if (in_array('--help', $args, true) || count($files) !== 2) {
    fwrite(count($files) === 2 ? STDOUT : STDERR, <<<HELP
Usage: php tools/api-tester/bin/compare-reports.php <a.json> <b.json> [--details]

Compares two api-tester reports recorded with --record-bodies, case by case.
  --details   list every difference (default: summary per endpoint and per kind)

Exit codes: 0 no differences, 1 differences, 2 usage or unreadable report.

HELP
    );
    exit(in_array('--help', $args, true) ? 0 : 2);
}

$reports = [];
foreach ($files as $file) {
    $report = is_file($file) ? json_decode(file_get_contents($file)) : null;
    if (!$report instanceof stdClass) {
        fwrite(STDERR, "Not a readable api-tester report: {$file}\n");
        exit(2);
    }
    $reports[] = $report;
}

$comparison = (new ApiTester\ReportComparer())->compare($reports[0], $reports[1]);

$format = function ($value) {
    return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES);
};

echo "a: {$files[0]}\nb: {$files[1]}\n";
echo "Cases compared: {$comparison->casesCompared()}\n";

if ($comparison->isClean()) {
    echo "No differences.\n";
    exit(0);
}

echo "\nDifferences per kind:\n";
foreach ($comparison->byKind() as $kind => $differences) {
    printf("  %-14s %d\n", $kind, count($differences));
}

echo "\nDifferences per endpoint:\n";
foreach ($comparison->byEndpoint() as $endpoint => $differences) {
    $kinds = array_count_values(array_column($differences, 'kind'));
    ksort($kinds);
    $parts = [];
    foreach ($kinds as $kind => $count) {
        $parts[] = "{$kind} {$count}";
    }
    echo "  {$endpoint}: " . implode(', ', $parts) . "\n";
    if ($details) {
        foreach ($differences as $difference) {
            $where = $difference['path'] === '' ? '' : " {$difference['path']}";
            echo "    [{$difference['kind']}] {$difference['id']}{$where}: "
                . $format($difference['a']) . ' -> ' . $format($difference['b']) . "\n";
        }
    }
}

echo "\n" . count($comparison->differences()) . " difference(s)"
    . ($details ? '' : '; rerun with --details to list them') . ".\n";
exit(1);
