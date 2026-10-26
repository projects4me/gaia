<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Docs\DocsBuilder;
use GaiaDev\Query\GraphReader;
use GaiaDev\Query\QueryException;

/**
 * `gaia-dev docs generate [--check]`: writes the pages under docs/generated from
 * the committed graph, or with --check lists the files that are stale, missing
 * or no longer generated, and fails.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DocsCommand
{
    private const DIRECTORY = 'docs/generated';

    /**
     * Files in the directory that are not pages.
     */
    private const KEEP = ['graph.json'];

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
     * @param bool $check compare with the files instead of writing them
     * @return int exit code: 0 written or up to date, 1 stale, missing or not written
     */
    public function run(string $root, bool $check = false): int
    {
        $root = rtrim($root, '/\\');
        try {
            $graph = GraphReader::fromFile($root . '/' . GraphExportCommand::DEFAULT_OUTPUT);
        } catch (QueryException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }
        $pages = (new DocsBuilder($root))->build($graph);

        return $check ? $this->check($root, $pages) : $this->write($root, $pages);
    }

    /**
     * @param string $root
     * @param string[] $pages
     * @return int
     */
    private function write(string $root, array $pages): int
    {
        $base = $root . '/' . self::DIRECTORY . '/';
        foreach ($this->existing($root) as $path) {
            if (!isset($pages[$path])) {
                unlink($base . $path);
            }
        }
        foreach ($pages as $path => $content) {
            if (!is_dir(dirname($base . $path)) && !mkdir(dirname($base . $path), 0775, true)) {
                fwrite($this->stderr, 'Cannot create ' . self::DIRECTORY . '/' . dirname($path) . "\n");
                return 1;
            }
            if (file_put_contents($base . $path, $content) === false) {
                fwrite($this->stderr, 'Cannot write ' . self::DIRECTORY . '/' . $path . "\n");
                return 1;
            }
        }
        fwrite($this->stdout, 'Wrote ' . count($pages) . ' pages to ' . self::DIRECTORY . "\n");

        return 0;
    }

    /**
     * Line endings are ignored, so a CRLF checkout on Windows is not stale.
     *
     * @param string $root
     * @param string[] $pages
     * @return int
     */
    private function check(string $root, array $pages): int
    {
        $base = $root . '/' . self::DIRECTORY . '/';
        $problems = [];
        foreach ($pages as $path => $content) {
            if (!is_file($base . $path)) {
                $problems[] = $path . ' is missing';
            } elseif (str_replace("\r\n", "\n", file_get_contents($base . $path)) !== $content) {
                $problems[] = $path . ' is stale';
            }
        }
        foreach ($this->existing($root) as $path) {
            if (!isset($pages[$path])) {
                $problems[] = $path . ' is no longer generated';
            }
        }

        if (!$problems) {
            fwrite($this->stdout, self::DIRECTORY . " is up to date\n");
            return 0;
        }
        fwrite(
            $this->stderr,
            self::DIRECTORY . ' is stale: ' . implode('; ', array_slice($problems, 0, 10))
            . (count($problems) > 10 ? '; and ' . (count($problems) - 10) . " more" : '')
            . "; run tools/gaia-dev/bin/gaia-dev docs generate and commit the result\n"
        );

        return 1;
    }

    /**
     * @param string $root
     * @return string[] generated files on disk, relative to docs/generated
     */
    private function existing(string $root): array
    {
        $base = $root . '/' . self::DIRECTORY;
        if (!is_dir($base)) {
            return [];
        }
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            if (!in_array($path, self::KEEP, true)) {
                $found[] = $path;
            }
        }
        sort($found);

        return $found;
    }
}
