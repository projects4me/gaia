<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

use GaiaDev\Extractor\FixtureExtractor;
use GaiaDev\Extractor\ProfileExtractor;

/**
 * Plans the source files a new resource touches (metadata, model, controller, models list, routes, generator
 * placeholders, harness and fixtures) and writes them together, restoring the originals when a write fails.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceScaffold
{
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
     * @param ResourceSpec $spec
     * @param array $roles profile name => role id for the profiles in the spec's ACL
     * @return string[] repository relative path => new file text
     * @throws ScaffoldException
     */
    public function plan(ResourceSpec $spec, array $roles): array
    {
        $registrar = new ResourceRegistrar();
        $seed = new ResourceSeed();
        $changes = (new ResourceRenderer($this->root))->render($spec);
        foreach (array_keys($changes) as $path) {
            if (is_file($this->root . '/' . $path)) {
                throw new ScaffoldException("{$path} already exists; {$spec->name} looks scaffolded already");
            }
        }
        if ($spec->parent !== null && !is_file($this->root . "/app/metadata/model/{$spec->parent}.php")) {
            throw new ScaffoldException("Parent model {$spec->parent} has no metadata file");
        }

        $changes[ResourceRegistrar::MODELS] = $registrar->models($spec, $this->read(ResourceRegistrar::MODELS));
        $changes[ResourceRegistrar::ROUTES] = $registrar->routes($spec, $this->read(ResourceRegistrar::ROUTES));
        if (is_file($this->root . '/' . ResourceRegistrar::GENERATOR)) {
            $changes[ResourceRegistrar::GENERATOR] = $registrar->generator($spec, $this->read(ResourceRegistrar::GENERATOR));
        }
        $changes[ProfileExtractor::SOURCE] = $seed->harness($spec, $this->read(ProfileExtractor::SOURCE), $roles);
        $changes[FixtureExtractor::SOURCE] = $seed->fixtures($spec, $this->read(FixtureExtractor::SOURCE));

        return $changes;
    }

    /**
     * @param string[] $changes repository relative path => new file text
     * @throws ScaffoldException
     */
    public function write(array $changes): void
    {
        $originals = [];
        foreach ($changes as $path => $text) {
            $file = $this->root . '/' . $path;
            $originals[$file] = is_file($file) ? file_get_contents($file) : null;
            $eol = $originals[$file] !== null && strpos($originals[$file], "\r\n") !== false ? "\r\n" : "\n";
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0777, true)) {
                $this->restore($originals);
                throw new ScaffoldException("Cannot create the directory of {$path}");
            }
            if (file_put_contents($file, $eol === "\n" ? $text : str_replace("\n", $eol, $text)) === false) {
                $this->restore($originals);
                throw new ScaffoldException("Cannot write {$path}; the files already written were restored");
            }
        }
    }

    /**
     * @param string $relative
     * @return string
     * @throws ScaffoldException
     */
    private function read(string $relative): string
    {
        $file = $this->root . '/' . $relative;
        if (!is_file($file)) {
            throw new ScaffoldException("Source file not found: {$relative}");
        }

        return file_get_contents($file);
    }

    /**
     * @param array $originals file => text, null when the file did not exist
     */
    private function restore(array $originals): void
    {
        foreach ($originals as $file => $text) {
            if ($text === null) {
                if (is_file($file)) {
                    unlink($file);
                }
            } else {
                file_put_contents($file, $text);
            }
        }
    }
}
