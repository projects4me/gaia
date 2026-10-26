<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev;

use GaiaDev\Command\GraphExportCommand;

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
     * @param string[] $argv
     * @param string $defaultRoot repository root used when --root is not given
     * @return int exit code
     */
    public static function main(array $argv, string $defaultRoot): int
    {
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

        if (array_slice($words, 0, 2) === ['graph', 'export'] && count($words) === 2) {
            $root = isset($options['root']) && is_string($options['root']) ? $options['root'] : $defaultRoot;
            if (isset($options['output']) && !is_string($options['output'])) {
                fwrite(STDERR, "--output needs a value: --output=<file>\n");
                return 1;
            }
            $output = isset($options['output']) ? $options['output'] : GraphExportCommand::DEFAULT_OUTPUT;
            return (new GraphExportCommand(STDOUT, STDERR))->run($root, $output, !empty($options['check']));
        }

        $known = $words === [] || isset($options['help']) || $words === ['help'];
        fwrite($known ? STDOUT : STDERR, self::usage());

        return $known ? 0 : 1;
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

Options:
  --root=<dir>            Repository root (default: this checkout)
  --output=<file>         Output path relative to the root (default: docs/generated/graph.json)

TXT;
    }
}
