#!/usr/bin/env php
<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Groups PHP deprecations and warnings from one or more PHP error logs and prints markdown.
 *
 * Usage: php tools/quality/deprecations-report.php [--fail-on-gaia] <log>...
 *
 * With --fail-on-gaia the exit code is 1 when any entry is in Gaia code (the CI gate); entries under
 * vendor are reported only.
 */

require __DIR__ . '/src/DeprecationGrouper.php';
require __DIR__ . '/src/MarkdownReport.php';

$args = array_slice($argv, 1);
$failOnGaia = in_array('--fail-on-gaia', $args, true);
$logs = array_values(array_diff($args, ['--fail-on-gaia']));
if (!$logs) {
    fwrite(STDERR, "Usage: php tools/quality/deprecations-report.php [--fail-on-gaia] <log>...\n");
    exit(2);
}

$grouper = new Quality\DeprecationGrouper();
foreach ($logs as $log) {
    if (!is_readable($log)) {
        fwrite(STDERR, "Cannot read {$log}\n");
        exit(1);
    }
    $grouper->addLines(new SplFileObject($log));
}
echo (new Quality\MarkdownReport())->render($grouper);

$gaiaEntries = $grouper->totals()['gaia'];
if ($failOnGaia && $gaiaEntries > 0) {
    fwrite(STDERR, "{$gaiaEntries} PHP deprecations or warnings in Gaia code: the gate fails.\n");
    exit(1);
}
