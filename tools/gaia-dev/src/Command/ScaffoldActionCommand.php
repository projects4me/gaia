<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Query\GraphReader;
use GaiaDev\Query\QueryException;
use GaiaDev\Scaffold\ActionScaffold;
use GaiaDev\Scaffold\ActionSpec;
use GaiaDev\Scaffold\ResourceScaffold;
use GaiaDev\Scaffold\ScaffoldException;

/**
 * `gaia-dev scaffold action <Controller> <list|get|post|patch|delete> [--domain=Class] [--dry-run]`: overrides
 * one standard action of an existing controller with a thin skeleton and adds its acl and client cases.
 * Names the domain class as the next thing to write.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldActionCommand
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
     * @param string[] $arguments the words after `scaffold action`
     * @param array $options parsed command line options
     * @param string $root repository root
     * @return int exit code
     */
    public function run(array $arguments, array $options, string $root): int
    {
        $root = rtrim($root, '/\\');
        try {
            if (count($arguments) !== 2) {
                throw new ScaffoldException('Usage: gaia-dev scaffold action <Controller> <list|get|post|patch|delete> [--domain=Class] [--dry-run]');
            }
            $spec = new ActionSpec($arguments[0], $arguments[1], $options);
            $graph = GraphReader::fromFile($root . '/' . GraphExportCommand::DEFAULT_OUTPUT);
            $scaffold = new ActionScaffold($root, $graph);
            $changes = $scaffold->plan($spec);
            $dry = !empty($options['dry-run']);
            if (!$dry) {
                (new ResourceScaffold($root))->write($changes);
            }
        } catch (ScaffoldException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        } catch (QueryException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }

        fwrite($this->stdout, ($dry ? 'would write' : 'wrote') . " {$spec->action()} of {$spec->model}Controller ({$spec->method()} {$spec->path()}, {$spec->permission()}):\n");
        foreach (array_keys($changes) as $path) {
            fwrite($this->stdout, "  {$path}\n");
        }
        foreach ($scaffold->notes() as $note) {
            fwrite($this->stdout, "note: {$note}\n");
        }
        fwrite($this->stdout, "next: write the domain class {$spec->domainClass()} and call it from {$spec->action()}\n");
        fwrite($this->stdout, "next: edit the asserted values, then run graph export, docs generate and check action\n");

        return 0;
    }
}
