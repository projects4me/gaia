<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Check;

/**
 * Regenerates `apis/backend.json` and the route snapshot with the committed generator, in a
 * scratch copy of just the inputs it reads, and reports the files that differ from the
 * committed ones. The checkout is never touched.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Check
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BackendCatalogDrift
{
    private const GENERATOR = 'tools/api-tester/bin/generate-backend-apis.php';

    /**
     * Files the generator writes, relative to the root.
     */
    private const OUTPUTS = ['tools/api-tester/apis/backend.json', 'tools/api-tester/sources/server-routes/routes.v1.json'];

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * Runs the generator in place, so backend.json and routes.v1.json follow the sources.
     *
     * @return string|null null when the repository has no generator, '' on success, else the generator's output
     */
    public function regenerate(): ?string
    {
        if (!is_file($this->root . '/' . self::GENERATOR)) {
            return null;
        }
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/' . self::GENERATOR) . ' 2>&1', $output, $code);

        return $code === 0 ? '' : trim(implode(' ', $output));
    }

    /**
     * @return string[]|null the problems, or null when the repository has no generator to run
     */
    public function problems(): ?array
    {
        if (!is_file($this->root . '/' . self::GENERATOR)) {
            return null;
        }
        $scratch = sys_get_temp_dir() . '/gaia-dev-backend-' . bin2hex(random_bytes(4));
        try {
            $this->stage($scratch);
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scratch . '/' . self::GENERATOR) . ' 2>&1', $output, $code);
            if ($code !== 0) {
                return ['backend.json cannot be regenerated: ' . trim(implode(' ', $output))];
            }
            $problems = [];
            foreach (self::OUTPUTS as $file) {
                $committed = is_file($this->root . '/' . $file) ? $this->normalise(file_get_contents($this->root . '/' . $file)) : null;
                if ($committed !== $this->normalise(file_get_contents($scratch . '/' . $file))) {
                    $problems[] = "{$file} is stale: run php " . self::GENERATOR;
                }
            }

            return $problems;
        } finally {
            $this->remove($scratch);
        }
    }

    /**
     * @param string $scratch
     */
    private function stage(string $scratch): void
    {
        $copies = [self::GENERATOR, 'core/config/routes.php'];
        foreach (glob($this->root . '/app/metadata/model/*.php') as $file) {
            $copies[] = 'app/metadata/model/' . basename($file);
        }
        foreach ($copies as $file) {
            $this->copy($this->root . '/' . $file, $scratch . '/' . $file);
        }
        foreach (['tools/api-tester/apis', 'tools/api-tester/sources/server-routes'] as $directory) {
            mkdir($scratch . '/' . $directory, 0777, true);
        }
    }

    /**
     * @param string $from
     * @param string $to
     */
    private function copy(string $from, string $to): void
    {
        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0777, true);
        }
        copy($from, $to);
    }

    /**
     * @param string $text
     * @return string the text with LF line endings, so a Windows checkout compares equal
     */
    private function normalise(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }

    /**
     * @param string $path
     */
    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
