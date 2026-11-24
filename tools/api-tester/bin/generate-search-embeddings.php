#!/usr/bin/env php
<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Gaia-specific (not part of the portable black-box core): writes the stored embeddings of the search fixture
 * (agaia-4896). Reads the fixture issues as JSON on stdin ([{id, subject, description}, ...], as the test database
 * holds them), builds each one's semantic text and hash with Gaia's own SemanticText and the Issue search block, embeds
 * the texts through Ares POST /embeddings (task document, as Ares embeds records) and writes the data file.
 *
 * Run it through harness/generate-search-embeddings.sh, which seeds the fixture and feeds this script.
 *
 * Usage: generate-search-embeddings.php --ares-url <url> --out <file> < records.json
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "generate-search-embeddings must be run from CLI\n");
    exit(1);
}

$apiTesterRoot = dirname(__DIR__);
$gaiaRoot = dirname($apiTesterRoot, 2);

require $apiTesterRoot . '/src/SearchEmbeddingFixture.php';
require $gaiaRoot . '/core/libs/search/SemanticText.php';
// SemanticText throws Gaia's exception on a PCRE error; load it from core/exceptions when that happens
spl_autoload_register(function ($class) use ($gaiaRoot) {
    $prefix = 'Gaia\\Exception\\';
    if (strpos($class, $prefix) === 0) {
        $file = $gaiaRoot . '/core/exceptions/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use ApiTester\SearchEmbeddingFixture;
use Gaia\Libraries\Search\SemanticText;

$options = getopt('', ['ares-url:', 'out:']);
if (empty($options['ares-url']) || empty($options['out'])) {
    fwrite(STDERR, "Usage: generate-search-embeddings.php --ares-url <url> --out <file> < records.json\n");
    exit(2);
}
$aresUrl = rtrim($options['ares-url'], '/');

$records = json_decode(stream_get_contents(STDIN), true);
if (!is_array($records) || $records === []) {
    fwrite(STDERR, "No fixture records on stdin: seed the search fixture first (harness/seed-search.sh).\n");
    exit(1);
}

$models = [];
require $gaiaRoot . '/app/metadata/model/Issue.php';
$semanticText = SemanticText::forBlock($models['Issue']['search']);

$embed = function (array $texts) use ($aresUrl) {
    $curl = curl_init($aresUrl . '/embeddings');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['texts' => $texts, 'task' => 'document']),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false || $status !== 200) {
        throw new \RuntimeException("Ares POST /embeddings failed at {$aresUrl}: " . ($error !== '' ? $error : "status {$status}"));
    }
    $answer = json_decode($body, true);
    if (!is_array($answer)) {
        throw new \RuntimeException('Ares POST /embeddings answered with a body that is not JSON.');
    }

    return $answer;
};

$fixture = new SearchEmbeddingFixture(
    function (array $record) use ($semanticText) {
        return $semanticText->text($record);
    },
    function ($text) {
        return SemanticText::hashOf($text);
    },
    $embed
);

try {
    $data = $fixture->build($records);
} catch (\RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

// One record per line keeps the file diffable without a line per vector element
$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;
$lines = [];
foreach ($data['records'] as $record) {
    $lines[] = json_encode($record, $flags);
}
$header = $data;
$header['records'] = [];
$head = json_encode($header, $flags | JSON_PRETTY_PRINT);
$written = false;
if ($head !== false && !in_array(false, $lines, true)) {
    // The header ends with `"records": []` and the closing brace: put the records between the brackets
    $json = substr($head, 0, -strlen("[]\n}")) . "[\n    " . implode(",\n    ", $lines) . "\n  ]\n}\n";
    $written = file_put_contents($options['out'], $json) !== false;
}
if (!$written) {
    fwrite(STDERR, "Could not write {$options['out']}\n");
    exit(1);
}
echo 'Wrote ' . count($data['records']) . " embeddings ({$data['model']}, {$data['dimensions']} dimensions) to {$options['out']}\n";
