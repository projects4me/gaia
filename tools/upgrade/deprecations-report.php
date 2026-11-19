#!/usr/bin/env php
<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Groups PHP deprecations and warnings from one or more PHP error logs and prints markdown.
 *
 * Usage: php tools/upgrade/deprecations-report.php <log>...
 */

require __DIR__ . '/src/DeprecationGrouper.php';
require __DIR__ . '/src/MarkdownReport.php';

$logs = array_slice($argv, 1);
if (!$logs) {
    fwrite(STDERR, "Usage: php tools/upgrade/deprecations-report.php <log>...\n");
    exit(2);
}

$grouper = new Upgrade\DeprecationGrouper();
foreach ($logs as $log) {
    if (!is_readable($log)) {
        fwrite(STDERR, "Cannot read {$log}\n");
        exit(1);
    }
    $grouper->addLines(new SplFileObject($log));
}
echo (new Upgrade\MarkdownReport())->render($grouper);
