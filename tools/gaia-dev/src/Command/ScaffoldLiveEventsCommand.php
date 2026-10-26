<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Scaffold\LiveEventsScaffold;
use GaiaDev\Scaffold\LiveEventsSpec;
use GaiaDev\Scaffold\ResourceScaffold;
use GaiaDev\Scaffold\ScaffoldException;

/**
 * `gaia-dev scaffold live-events <Model> [--events=created,updated,deleted]`: writes the component, the
 * EventNames constants, the controller wiring and the test cases, and prints the names Hermes must gain.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldLiveEventsCommand
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
     * @param string[] $arguments the words after `scaffold live-events`
     * @param array $options parsed command line options
     * @param string $root repository root
     * @return int exit code
     */
    public function run(array $arguments, array $options, string $root): int
    {
        try {
            if (count($arguments) !== 1) {
                throw new ScaffoldException('Usage: gaia-dev scaffold live-events <Model> [--events=created,updated,deleted] [--prefix=idea.message] [--fields=a,b] [--parent=ideaId:Idea] [--dry-run]');
            }
            $spec = new LiveEventsSpec($arguments[0], $options);
            $scaffold = new LiveEventsScaffold($root);
            $changes = $scaffold->plan($spec);
            $dry = !empty($options['dry-run']);
            fwrite($this->stdout, ($dry ? 'would write' : 'wrote') . " live events of {$spec->model}:\n");
            foreach (array_keys($changes) as $path) {
                fwrite($this->stdout, "  {$path}\n");
            }
            if (!$dry) {
                (new ResourceScaffold($root))->write($changes);
            }
        } catch (ScaffoldException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }

        fwrite($this->stdout, "Hermes contract: add these names to EVENT_ALLOWLIST in hermes/src/contract/names.js, in a linked PR:\n");
        foreach ($scaffold->hermesNames($spec) as $name) {
            fwrite($this->stdout, "  {$name}\n");
        }
        if ($dry) {
            return 0;
        }
        fwrite($this->stdout, "todo: set the tracked fields in {$spec->componentClass()} and the real payload asserts in " . LiveEventsSpec::TEST_PATH . "\n");
        fwrite($this->stdout, "todo: run: gaia-dev check live-events\n");

        return 0;
    }
}
