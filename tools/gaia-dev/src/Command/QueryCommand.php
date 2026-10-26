<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Command;

use GaiaDev\Query\AclQuery;
use GaiaDev\Query\AssertionsQuery;
use GaiaDev\Query\CasesQuery;
use GaiaDev\Query\FixturesQuery;
use GaiaDev\Query\GraphReader;
use GaiaDev\Query\ModelQuery;
use GaiaDev\Query\ProfilesQuery;
use GaiaDev\Query\Query;
use GaiaDev\Query\QueryException;
use GaiaDev\Query\Renderer;
use GaiaDev\Query\RouteQuery;

/**
 * `gaia-dev model|route|acl|cases|assertions|profiles|fixtures ...`: loads the
 * committed graph, runs the query and prints terse text (or JSON with --json).
 * An unknown name prints a one-line hint on stderr and exits 1.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Command
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class QueryCommand
{
    /**
     * @return Query[] command name => query
     */
    public static function queries(): array
    {
        return [
            'model' => new ModelQuery(),
            'route' => new RouteQuery(),
            'acl' => new AclQuery(),
            'cases' => new CasesQuery(),
            'assertions' => new AssertionsQuery(),
            'profiles' => new ProfilesQuery(),
            'fixtures' => new FixturesQuery(),
        ];
    }

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
     * @param string $command a key of queries()
     * @param string[] $arguments
     * @param array $options
     * @param string $root repository root
     * @return int exit code
     */
    public function run(string $command, array $arguments, array $options, string $root): int
    {
        $queries = self::queries();
        try {
            $graph = GraphReader::fromFile(rtrim($root, '/\\') . '/' . GraphExportCommand::DEFAULT_OUTPUT);
            $answer = $queries[$command]->run($graph, $arguments, $options);
        } catch (QueryException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n");
            return 1;
        }
        fwrite($this->stdout, !empty($options['json']) ? Renderer::json($answer) : Renderer::text($answer));

        return 0;
    }
}
