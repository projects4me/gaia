<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Extractor\CatalogExtractor;
use GaiaDev\Extractor\FixtureExtractor;
use GaiaDev\Extractor\ProfileExtractor;
use GaiaDev\Query\GraphReader;
use GaiaDev\Query\QueryException;
use GaiaDev\Scaffold\CaseBundle;
use GaiaDev\Scaffold\CaseRequest;
use GaiaDev\Scaffold\CatalogAppender;
use GaiaDev\Scaffold\ProfileScaffold;
use GaiaDev\Scaffold\ScaffoldException;

/**
 * `gaia-dev scaffold api-cases ...`: appends the cases for an endpoint (or a JSON list of
 * cases) to an api-tester catalog in that file's own style, or defines a new auth profile
 * in the harness and the fixtures together. Everything is computed before anything is
 * written, so a failure leaves the files as they were.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldCommand
{
    /**
     * @var resource
     */
    private $stdout;

    /**
     * @var resource
     */
    private $stderr;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct($stdout, $stderr)
    {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * @param string[] $arguments METHOD and endpoint, or none with --cases-file or --new-profile
     * @param array $options
     * @param string $root repository root
     * @return int exit code
     */
    public function run(array $arguments, array $options, string $root): int
    {
        $root = rtrim($root, '/\\');
        try {
            if (isset($options['new-profile'])) {
                return $this->newProfile($options, $root);
            }
            $catalog = isset($options['catalog']) ? $options['catalog'] : 'client';
            $path = $root . '/' . CatalogExtractor::SOURCE_DIR . '/' . $catalog . '.json';
            if (!preg_match('/^[a-z]+$/', $catalog) || !is_file($path)) {
                throw new ScaffoldException("No catalog {$catalog} under tools/api-tester/apis");
            }
            $notes = [];
            if (isset($options['cases-file'])) {
                $cases = $this->casesFromFile($options['cases-file']);
            } else {
                if (count($arguments) !== 2) {
                    throw new ScaffoldException('Usage: gaia-dev scaffold api-cases <METHOD> <endpoint> [--profile=] [--assert=] [--catalog=client|acl]');
                }
                $graph = GraphReader::fromFile($root . '/' . GraphExportCommand::DEFAULT_OUTPUT);
                $bundle = new CaseBundle($graph);
                $assertions = isset($options['assert']) ? CaseRequest::parseAssertions($options['assert']) : [];
                $profile = isset($options['profile']) ? $options['profile'] : null;
                $cases = $bundle->build(new CaseRequest($arguments[0], $arguments[1], $profile, $catalog, $assertions));
                $notes = $bundle->notes();
            }
            $text = (new CatalogAppender())->append($catalog, file_get_contents($path), $cases);
        } catch (ScaffoldException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        } catch (QueryException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }

        $relative = CatalogExtractor::SOURCE_DIR . '/' . $catalog . '.json';
        if (empty($options['dry-run'])) {
            if (file_put_contents($path, $text) === false) {
                fwrite($this->stderr, "Cannot write {$path}
");
                return 1;
            }
        }
        fwrite($this->stdout, (empty($options['dry-run']) ? 'appended ' : 'would append ') . count($cases) . " case(s) to {$relative}:\n");
        foreach ($cases as $case) {
            fwrite($this->stdout, '  ' . $case['id'] . "\n");
        }
        foreach ($notes as $note) {
            fwrite($this->stdout, "note: {$note}\n");
        }
        fwrite($this->stdout, "next: edit the asserted values, then run graph export, docs generate and check api-cases\n");

        return 0;
    }

    /**
     * @param array $options
     * @param string $root
     * @return int
     * @throws ScaffoldException
     */
    private function newProfile(array $options, string $root): int
    {
        $harnessPath = $root . '/' . ProfileExtractor::SOURCE;
        $fixturesPath = $root . '/' . FixtureExtractor::SOURCE;
        foreach ([$harnessPath, $fixturesPath] as $file) {
            if (!is_file($file)) {
                throw new ScaffoldException("Source file not found: {$file}");
            }
        }
        list($harness, $fixtures) = (new ProfileScaffold())->define(
            (string) $options['new-profile'],
            isset($options['role']) ? (string) $options['role'] : '',
            isset($options['permissions']) ? (string) $options['permissions'] : '',
            file_get_contents($harnessPath),
            file_get_contents($fixturesPath)
        );
        if (empty($options['dry-run'])) {
            $original = file_get_contents($harnessPath);
            if (file_put_contents($harnessPath, $harness) === false) {
                throw new ScaffoldException("Cannot write {$harnessPath}");
            }
            if (file_put_contents($fixturesPath, $fixtures) === false) {
                file_put_contents($harnessPath, $original);
                throw new ScaffoldException("Cannot write {$fixturesPath}; {$harnessPath} was restored");
            }
        }
        fwrite($this->stdout, (empty($options['dry-run']) ? 'defined' : 'would define') . " profile {$options['new-profile']} in "
            . ProfileExtractor::SOURCE . " and " . FixtureExtractor::SOURCE . "\n");
        fwrite($this->stdout, "next: run prepare-test-db.sh, graph export and docs generate\n");

        return 0;
    }

    /**
     * @param string $file JSON file holding a case or a list of cases
     * @return array[]
     * @throws ScaffoldException
     */
    private function casesFromFile(string $file): array
    {
        if (!is_file($file)) {
            throw new ScaffoldException("Cases file not found: {$file}");
        }
        $cases = $this->arrays(json_decode(file_get_contents($file)));
        if (!is_array($cases) || $cases === []) {
            throw new ScaffoldException("{$file} must hold a JSON case or a non-empty list of cases");
        }
        if (isset($cases['id'])) {
            $cases = [$cases];
        }
        foreach ($cases as $case) {
            if (!is_array($case) || !isset($case['id'], $case['method'], $case['path'])) {
                throw new ScaffoldException('Every case needs at least id, method and path');
            }
        }

        return array_values($cases);
    }

    /**
     * Decoded JSON as arrays, except that an empty object stays one (it would otherwise be written back as []).
     *
     * @param mixed $value
     * @return mixed
     */
    private function arrays($value)
    {
        if ($value instanceof \stdClass) {
            $items = get_object_vars($value);

            return $items === [] ? $value : array_map([$this, 'arrays'], $items);
        }

        return is_array($value) ? array_map([$this, 'arrays'], $value) : $value;
    }
}
