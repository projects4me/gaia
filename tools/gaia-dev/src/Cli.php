<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev;

use GaiaDev\Command\GraphExportCommand;
use GaiaDev\Command\QueryCommand;

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
    private const QUERY_OPTIONS = [
        'root' => true, 'json' => false, 'method' => true, 'profile' => true, 'all' => false,
    ];

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
            $error = self::validateOptions($options, self::QUERY_OPTIONS);
            if ($error !== null) {
                fwrite($stderr, $error . "\n");
                return 1;
            }
            $root = isset($options['root']) ? $options['root'] : $defaultRoot;

            return (new QueryCommand($stdout, $stderr))->run($words[0], array_slice($words, 1), $options, $root);
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
  model <Name>            Table, fields, relationships, behaviours, ACL, routes, components, cases
  route <endpoint|path>   Controller, action, hook sequence and the components that fire
  acl <resource> [profile]  What each auth profile may do on the resource, or one profile's row
  cases <endpoint|id>     api-tester cases (--method=, --profile=, --all)
  assertions [name]       The assertion vocabulary with one real example each
  profiles [name]         Auth profiles: role, grants, case counts
  fixtures [name]         Placeholder names and where they come from

Queries read docs/generated/graph.json and take --json for machine output.

Options:
  --root=<dir>            Repository root (default: this checkout)
  --output=<file>         Output path relative to the root (default: docs/generated/graph.json)

TXT;
    }
}
