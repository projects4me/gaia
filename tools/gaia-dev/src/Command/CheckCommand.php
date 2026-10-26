<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Check\ApiCasesCheck;
use GaiaDev\Check\BackendCatalogDrift;
use GaiaDev\Query\GraphReader;
use GaiaDev\Query\QueryException;

/**
 * `gaia-dev check api-cases [<endpoint>]`: prints each problem found in the api-tester
 * catalogs and exits 1 when there is one. For CI and for `ai ship` before a Gaia PR.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CheckCommand
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
     * @param string|null $endpoint a resource name or path prefix; the backend.json comparison runs only without one
     * @param string $root repository root
     * @return int exit code: 0 consistent, 1 problems found or no graph
     */
    public function run(?string $endpoint, string $root): int
    {
        try {
            $graph = GraphReader::fromFile(rtrim($root, '/\\') . '/' . GraphExportCommand::DEFAULT_OUTPUT);
        } catch (QueryException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }

        $problems = (new ApiCasesCheck($graph))->problems($endpoint);
        if ($endpoint === null) {
            $drift = (new BackendCatalogDrift($root))->problems();
            if ($drift === null) {
                fwrite($this->stdout, "skipped: no backend catalog generator in this repository\n");
            } else {
                $problems = array_merge($problems, $drift);
            }
        }

        foreach ($problems as $problem) {
            fwrite($this->stderr, $problem . "\n");
        }
        if ($problems) {
            fwrite($this->stderr, count($problems) . " api-cases problem(s)\n");
            return 1;
        }
        fwrite($this->stdout, "api-cases: ok\n");

        return 0;
    }
}
