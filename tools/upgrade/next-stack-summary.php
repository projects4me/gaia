#!/usr/bin/env php
<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Prints the markdown summary of the CI next-stack job from a folder laid out by the compare step:
 *
 *   current/schema.sql, next/schema.sql, schema.diff
 *   next/phpunit-junit.xml, next/dry-run.txt
 *   next/api-tester-{client,backend,acl}-report.json, compare-{client,backend,acl}.txt
 *   deprecations.md
 *
 * Usage: php tools/upgrade/next-stack-summary.php <folder>
 * Missing files show as "n/a" with the reason; the script itself only fails on a bad folder.
 */

require __DIR__ . '/src/NextStackFindings.php';
require __DIR__ . '/src/NextStackSummary.php';

$dir = $argv[1] ?? null;
if ($dir === null || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php tools/upgrade/next-stack-summary.php <folder>\n");
    exit(2);
}

$read = function ($file) use ($dir) {
    $path = "{$dir}/{$file}";
    return is_readable($path) ? file_get_contents($path) : null;
};

$findings = new Upgrade\NextStackFindings();
$summary = new Upgrade\NextStackSummary();
$summary->add('PHPUnit failures', 'upgrade-next/phpunit-junit.xml', $findings->phpunitFailures($read('next/phpunit-junit.xml')))
    ->add('Dry-run statements', 'upgrade-next/dry-run.txt', $findings->dryRunStatements($read('next/dry-run.txt')))
    ->add(
        'Schema differences',
        'upgrade-report/schema.diff',
        $findings->schemaDifferences(
            $read('schema.diff'),
            $read('current/schema.sql') !== null && $read('next/schema.sql') !== null
        )
    );
foreach (['client', 'backend', 'acl'] as $mode) {
    $summary->add(
        "api-tester {$mode} failures",
        "upgrade-next/api-tester-{$mode}-report.json",
        $findings->apiTesterFailures($read("next/api-tester-{$mode}-report.json"))
    );
}
foreach (['client', 'backend', 'acl'] as $mode) {
    $summary->add(
        "Response differences ({$mode})",
        "upgrade-report/compare-{$mode}.txt",
        $findings->responseDifferences($read("compare-{$mode}.txt"))
    );
}
$summary->add('PHP deprecations and warnings', 'upgrade-report/deprecations.md', $findings->deprecations($read('deprecations.md')));
$summary->add('Implicitly nullable parameters (Gaia code)', 'upgrade-report/deprecations.md', $findings->implicitlyNullable($read('deprecations.md')));

echo $summary->render();
