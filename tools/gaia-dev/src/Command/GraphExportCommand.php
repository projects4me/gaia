<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Graph\GraphBuilder;
use GaiaDev\Graph\GraphJson;

/**
 * `gaia-dev graph export [--check] [--root <repo>] [--output <file>]`: writes
 * the graph to docs/generated/graph.json, or with --check compares it with the
 * committed file and fails when that is stale.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GraphExportCommand
{
    public const DEFAULT_OUTPUT = 'docs/generated/graph.json';

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
     * @param string $root repository root
     * @param string $relative output path relative to the root
     * @param bool $check compare with the file instead of writing it
     * @return int exit code: 0 written or up to date, 1 stale, missing or not written
     */
    public function run(string $root, string $relative = self::DEFAULT_OUTPUT, bool $check = false): int
    {
        $path = rtrim($root, '/\\') . '/' . $relative;
        $json = GraphJson::encode((new GraphBuilder($root))->build());

        if ($check) {
            return $this->check($path, $relative, $json);
        }

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true)) {
            fwrite($this->stderr, "Cannot create " . dirname($relative) . "\n");
            return 1;
        }
        if (file_put_contents($path, $json) === false) {
            fwrite($this->stderr, "Cannot write {$relative}\n");
            return 1;
        }
        fwrite($this->stdout, "Wrote {$relative}\n");

        return 0;
    }

    /**
     * Line endings are ignored, so a CRLF checkout on Windows is not stale.
     *
     * @param string $path
     * @param string $relative
     * @param string $json
     * @return int
     */
    private function check(string $path, string $relative, string $json): int
    {
        $committed = is_file($path) ? str_replace("\r\n", "\n", file_get_contents($path)) : null;

        if ($committed === $json) {
            fwrite($this->stdout, "{$relative} is up to date\n");
            return 0;
        }

        fwrite(
            $this->stderr,
            ($committed === null ? "{$relative} is missing" : "{$relative} is stale")
            . "; run tools/gaia-dev/bin/gaia-dev graph export and commit the result\n"
        );

        return 1;
    }
}
