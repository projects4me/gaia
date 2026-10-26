<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev;

use GaiaDev\Command\CheckCommand;
use GaiaDev\Command\DocsCommand;
use GaiaDev\Command\GraphExportCommand;
use GaiaDev\Command\QueryCommand;
use GaiaDev\Command\ScaffoldCommand;

/**
 * Parses the gaia-dev command line and dispatches to the command.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Cli
{
    /**
     * Options each command accepts: name => true when it needs a value.
     */
    private const EXPORT_OPTIONS = ['root' => true, 'output' => true, 'check' => false];
    private const QUERY_OPTIONS = ['root' => true, 'json' => false];
    private const SCAFFOLD_OPTIONS = [
        'root' => true, 'profile' => true, 'assert' => true, 'catalog' => true, 'cases-file' => true,
        'new-profile' => true, 'role' => true, 'permissions' => true, 'dry-run' => false,
    ];
    private const CASES_OPTIONS = ['method' => true, 'profile' => true, 'all' => false];

    /**
     * @param string[] $argv
     * @param string $defaultRoot repository root used when --root is not given
     * @param resource|null $stdout defaults to STDOUT
     * @param resource|null $stderr defaults to STDERR
     * @return int exit code
     */
    public static function main(array $argv, string $defaultRoot, $stdout = null, $stderr = null): int
    {
        $stdout = $stdout ?: STDOUT;
        $stderr = $stderr ?: STDERR;
        $words = [];
        $options = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (strpos($arg, '--') !== 0) {
                $words[] = $arg;
                continue;
            }
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = isset($parts[1]) ? $parts[1] : true;
        }

        if ($words === [] || $words === ['help'] || isset($options['help'])) {
            fwrite($stdout, self::usage());
            return 0;
        }

        if (isset(QueryCommand::queries()[$words[0]])) {
            $error = self::validateOptions(
                $options,
                $words[0] === 'cases' ? array_merge(self::QUERY_OPTIONS, self::CASES_OPTIONS) : self::QUERY_OPTIONS
            );
            if ($error !== null) {
                fwrite($stderr, $error . "\n");
                return 1;
            }
            $root = isset($options['root']) ? $options['root'] : $defaultRoot;

            return (new QueryCommand($stdout, $stderr))->run($words[0], array_slice($words, 1), $options, $root);
        }

        if ($words[0] === 'scaffold' && ($words[1] ?? null) === 'api-cases') {
            $error = self::validateOptions($options, self::SCAFFOLD_OPTIONS);
            if ($error !== null) {
                fwrite($stderr, $error . "\n");
                return 1;
            }
            $root = isset($options['root']) ? $options['root'] : $defaultRoot;

            return (new ScaffoldCommand($stdout, $stderr))->run(array_slice($words, 2), $options, $root);
        }

        if ($words[0] === 'check' && ($words[1] ?? null) === 'api-cases') {
            $error = self::validateOptions($options, ['root' => true]);
            if ($error !== null) {
                fwrite($stderr, $error . "\n");
                return 1;
            }
            $root = isset($options['root']) ? $options['root'] : $defaultRoot;

            return (new CheckCommand($stdout, $stderr))->run($words[2] ?? null, $root);
        }

        if ($words === ['docs', 'generate']) {
            $error = self::validateOptions($options, ['root' => true, 'check' => false]);
            if ($error !== null) {
                fwrite($stderr, $error . "\n");
                return 1;
            }
            $root = isset($options['root']) ? $options['root'] : $defaultRoot;

            return (new DocsCommand($stdout, $stderr))->run($root, !empty($options['check']));
        }

        if ($words !== ['graph', 'export']) {
            fwrite($stderr, self::usage());
            return 1;
        }

        $error = self::validateOptions($options, self::EXPORT_OPTIONS);
        if ($error !== null) {
            fwrite($stderr, $error . "\n");
            return 1;
        }

        $root = isset($options['root']) ? $options['root'] : $defaultRoot;
        $output = isset($options['output']) ? $options['output'] : GraphExportCommand::DEFAULT_OUTPUT;
        try {
            return (new GraphExportCommand($stdout, $stderr))->run($root, $output, !empty($options['check']));
        } catch (\Throwable $e) {
            fwrite($stderr, $e->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * @param array $options parsed options, value true when given without =
     * @param array $allowed option name => whether it needs a value
     * @return string|null the error, or null when the options are valid
     */
    private static function validateOptions(array $options, array $allowed): ?string
    {
        foreach ($options as $name => $value) {
            if (!array_key_exists($name, $allowed)) {
                return "Unknown option --{$name}";
            }
            if ($allowed[$name] && !is_string($value)) {
                return "--{$name} needs a value: --{$name}=<value>";
            }
            if (!$allowed[$name] && $value !== true) {
                return "--{$name} takes no value";
            }
        }

        return null;
    }

    /**
     * @return string
     */
    private static function usage(): string
    {
        return <<<TXT
Usage: gaia-dev <command> [options]

Commands:
  graph export            Write docs/generated/graph.json from the repository sources
  graph export --check    Exit 1 when the committed graph.json is stale
  docs generate           Write the pages under docs/generated from the committed graph
  docs generate --check   Exit 1 when the committed pages are stale
  model <Name>            Table, fields, relationships, behaviours, ACL, routes, components, cases
  route <endpoint|path>   Controller, action, hook sequence and the components that fire
  acl <resource> [profile]  What each auth profile may do on the resource, or one profile's row
  cases <endpoint|id>     api-tester cases (--method=, --profile=, --all)
  assertions [name]       The assertion vocabulary with one real example each
  profiles [name]         Auth profiles: role, grants, case counts
  fixtures [name]         Placeholder names and where they come from
  scaffold api-cases <METHOD> <endpoint>
                          Append the cases for a REST endpoint to a catalog in its own style
                          (--profile=, --assert=name=value,..., --catalog=client|acl, --dry-run)
  scaffold api-cases --cases-file=<json> --catalog=<name>
                          Append the cases in a JSON file, matching the catalog's style
  scaffold api-cases --new-profile=<name> --role=<role> --permissions=<resource.action=0|1|2,...>
                          Define an auth profile in the harness seed and the fixtures together
  check api-cases [<endpoint>]
                          Exit 1 when acl cases, placeholders, assertion names or backend.json are inconsistent

Queries read docs/generated/graph.json and take --json for machine output.

Options:
  --root=<dir>            Repository root (default: this checkout)
  --output=<file>         Output path relative to the root (default: docs/generated/graph.json)

TXT;
    }
}
