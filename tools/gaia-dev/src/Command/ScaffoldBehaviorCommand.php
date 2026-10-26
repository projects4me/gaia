<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Scaffold\BehaviorScaffold;
use GaiaDev\Scaffold\BehaviorSpec;
use GaiaDev\Scaffold\ResourceScaffold;
use GaiaDev\Scaffold\ScaffoldException;

/**
 * `gaia-dev scaffold behavior <name> --model=<Model> [--events=a,b]`: writes the behaviour class, its failing
 * unit test and the registration in the model's metadata.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldBehaviorCommand
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
     * @param string[] $arguments the words after `scaffold behavior`
     * @param array $options parsed command line options
     * @param string $root repository root
     * @return int exit code
     */
    public function run(array $arguments, array $options, string $root): int
    {
        try {
            if (count($arguments) !== 1) {
                throw new ScaffoldException('Usage: gaia-dev scaffold behavior <name> --model=<Model> [--events=beforeSave,afterFetch,...] [--dry-run]');
            }
            $spec = new BehaviorSpec($arguments[0], $options);
            $changes = (new BehaviorScaffold($root))->plan($spec);
            $dry = !empty($options['dry-run']);
            fwrite($this->stdout, ($dry ? 'would write' : 'wrote') . " {$spec->name}:\n");
            foreach (array_keys($changes) as $path) {
                fwrite($this->stdout, "  {$path}\n");
            }
            if ($dry) {
                return 0;
            }
            (new ResourceScaffold($root))->write($changes);
        } catch (ScaffoldException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }

        fwrite($this->stdout, "todo: write the failing case in {$spec->testPath()}, then the rule in {$spec->classPath()}\n");
        fwrite($this->stdout, "todo: order matters in the metadata's behaviors list; move {$spec->name} if it must run earlier\n");
        fwrite($this->stdout, "todo: run the test directly (the suite does not run tests/models): p4m test phpunit {$spec->testPath()}\n");
        fwrite($this->stdout, "todo: add api cases for the API-visible effect; run: gaia-dev check behavior\n");

        return 0;
    }
}
