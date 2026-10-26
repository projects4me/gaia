<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Check\BackendCatalogDrift;
use GaiaDev\Extractor\CatalogExtractor;
use GaiaDev\Query\GraphReader;
use GaiaDev\Query\QueryException;
use GaiaDev\Scaffold\CaseBundle;
use GaiaDev\Scaffold\CaseRequest;
use GaiaDev\Scaffold\CatalogAppender;
use GaiaDev\Scaffold\ResourceScaffold;
use GaiaDev\Scaffold\ResourceSpec;
use GaiaDev\Scaffold\ScaffoldException;

/**
 * `gaia-dev scaffold resource <Name>`: writes every touchpoint of a new resource, regenerates the backend
 * catalog and the graph, adds the acl cases for the profiles named in --acl and prints what is left by hand.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldResourceCommand
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
     * @param string[] $arguments the words after `scaffold resource`
     * @param array $options parsed command line options
     * @param string $root repository root
     * @return int exit code
     */
    public function run(array $arguments, array $options, string $root): int
    {
        $root = rtrim($root, '/\\');
        try {
            if (count($arguments) !== 1) {
                throw new ScaffoldException('Usage: gaia-dev scaffold resource <Name> [--fields=name:type[:length][:required][:default=x],...] [--parent=Project] [--behaviors=a,b] [--acl=profile:allow|members|deny,...] [--table=] [--dry-run]');
            }
            $spec = new ResourceSpec($arguments[0], $options);
            $graph = GraphReader::fromFile($root . '/' . GraphExportCommand::DEFAULT_OUTPUT);
            $scaffold = new ResourceScaffold($root);
            $changes = $scaffold->plan($spec, $this->roles($graph, $spec));
        } catch (ScaffoldException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        } catch (QueryException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }

        $dry = !empty($options['dry-run']);
        fwrite($this->stdout, ($dry ? 'would write' : 'wrote') . " {$spec->name}:\n");
        foreach (array_keys($changes) as $path) {
            fwrite($this->stdout, "  {$path}\n");
        }
        if ($dry) {
            return 0;
        }

        try {
            $scaffold->write($changes);
        } catch (ScaffoldException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }

        $left = $this->regenerate($root, $spec);
        foreach ($left as $item) {
            fwrite($this->stdout, "todo: {$item}\n");
        }
        foreach ($this->todo($spec) as $item) {
            fwrite($this->stdout, "todo: {$item}\n");
        }

        return 0;
    }

    /**
     * Regenerates what derives from the sources and adds the acl cases.
     *
     * @param string $root
     * @param ResourceSpec $spec
     * @return string[] what could not be done
     */
    private function regenerate(string $root, ResourceSpec $spec): array
    {
        $left = [];
        $generator = new BackendCatalogDrift($root);
        $output = $generator->regenerate();
        if ($output === null) {
            $left[] = 'no backend catalog generator in this repository; regenerate backend.json by hand';
        } elseif ($output !== '') {
            $left[] = 'backend.json was not regenerated: ' . $output;
        } else {
            fwrite($this->stdout, "regenerated backend.json and routes.v1.json\n");
        }

        $quiet = fopen('php://memory', 'w+');
        (new GraphExportCommand($quiet, $quiet))->run($root);
        if ($spec->acl !== []) {
            $left = array_merge($left, $this->aclCases($root, $spec));
            (new GraphExportCommand($quiet, $quiet))->run($root);
        }
        (new DocsCommand($quiet, $quiet))->run($root);
        fclose($quiet);
        fwrite($this->stdout, "regenerated docs/generated/graph.json and the pages\n");

        return $left;
    }

    /**
     * @param string $root
     * @param ResourceSpec $spec
     * @return string[] what could not be done
     */
    private function aclCases(string $root, ResourceSpec $spec): array
    {
        $path = $root . '/' . CatalogExtractor::SOURCE_DIR . '/acl.json';
        $left = [];
        try {
            $bundle = new CaseBundle(GraphReader::fromFile($root . '/' . GraphExportCommand::DEFAULT_OUTPUT));
            $cases = [];
            foreach (array_keys($spec->acl) as $profile) {
                foreach ([$spec->resource, $spec->resource . '/{' . $spec->fixtureKey() . '}'] as $endpoint) {
                    $cases = array_merge($cases, $bundle->build(new CaseRequest('GET', $endpoint, $profile, 'acl')));
                }
            }
            $text = (new CatalogAppender())->append('acl', file_get_contents($path), $cases);
            file_put_contents($path, $text);
            fwrite($this->stdout, 'appended ' . count($cases) . " acl case(s) to tools/api-tester/apis/acl.json\n");
            foreach ($bundle->notes() as $note) {
                $left[] = $note;
            }
        } catch (ScaffoldException $e) {
            $left[] = 'acl cases: ' . $e->getMessage();
        } catch (QueryException $e) {
            $left[] = 'acl cases: ' . $e->getMessage();
        }

        return $left;
    }

    /**
     * @param GraphReader $graph
     * @param ResourceSpec $spec
     * @return array profile name => role id
     * @throws ScaffoldException
     */
    private function roles(GraphReader $graph, ResourceSpec $spec): array
    {
        $roles = [];
        foreach (array_keys($spec->acl) as $profile) {
            try {
                $node = $graph->find('profile', $profile);
            } catch (QueryException $e) {
                throw new ScaffoldException($e->getMessage());
            }
            if (empty($node['attributes']['roleId'])) {
                throw new ScaffoldException("Profile {$profile} has no role to give permissions to");
            }
            $roles[$profile] = $node['attributes']['roleId'];
        }

        return $roles;
    }

    /**
     * @param ResourceSpec $spec
     * @return string[] what is left to do by hand
     */
    private function todo(ResourceSpec $spec): array
    {
        $todo = [];
        if ($spec->parent !== null) {
            $todo[] = "add the hasMany relationship for {$spec->name} to app/metadata/model/{$spec->parent}.php (only when the feature needs it)";
        }
        $todo[] = 'behaviour logic (Bundle D) and live events (Bundle E), if the resource needs them';
        $todo[] = 'run the migration from this checkout, then the full suite; edit the asserted values of the new cases';
        $todo[] = 'permissions for existing roles are granted by the reviewer; no permission seed SQL';
        $todo[] = "run: gaia-dev check resource {$spec->name}";

        return $todo;
    }
}
