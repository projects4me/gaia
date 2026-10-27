<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

use GaiaDev\Extractor\CatalogExtractor;
use GaiaDev\Query\GraphReader;

/**
 * Plans `gaia-dev scaffold action`: the action override in the controller plus the acl and client cases of the
 * verb's standard endpoint, built by the api-cases scaffolder. No route is written; Gaia has no custom routes.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActionScaffold
{
    /**
     * @var string
     */
    private $root;

    /**
     * @var GraphReader
     */
    private $graph;

    /**
     * @var string[]
     */
    private $notes = [];

    /**
     * @param string $root repository root
     * @param GraphReader $graph
     */
    public function __construct(string $root, GraphReader $graph)
    {
        $this->root = rtrim($root, '/\\');
        $this->graph = $graph;
    }

    /**
     * @return string[] things the author still has to settle by hand
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * @param ActionSpec $spec
     * @return string[] repository relative path => new file text
     * @throws ScaffoldException
     */
    public function plan(ActionSpec $spec): array
    {
        $this->notes = [];
        $changes = [$spec->controllerPath() => $this->controller($spec)];
        foreach (['acl', 'client'] as $catalog) {
            $path = CatalogExtractor::SOURCE_DIR . '/' . $catalog . '.json';
            $text = $this->read($path);
            $bundle = new CaseBundle($this->graph);
            $cases = $bundle->build(new CaseRequest($spec->method(), $spec->path(), null, $catalog));
            $this->notes = array_merge($this->notes, $bundle->notes());
            $fresh = array_values(array_filter($cases, function ($case) use ($text) {
                return strpos($text, '"id": ' . json_encode($case['id'])) === false;
            }));
            if (count($fresh) < count($cases)) {
                $this->notes[] = "{$catalog} already has " . (count($cases) - count($fresh)) . ' of these case(s); they were left alone';
            }
            if ($fresh !== []) {
                $changes[$path] = (new CatalogAppender())->append($catalog, $text, $fresh);
            }
        }

        return $changes;
    }

    /**
     * @param ActionSpec $spec
     * @return string the controller with the override added
     * @throws ScaffoldException
     */
    private function controller(ActionSpec $spec): string
    {
        $path = $spec->controllerPath();
        $text = $this->read($path);
        if (preg_match('/function\s+' . $spec->action() . '\s*\(/', $text)) {
            throw new ScaffoldException("{$path} already overrides {$spec->action()}");
        }
        $end = strrpos($text, "\n}");
        if ($end === false) {
            throw new ScaffoldException("{$path} has no class body to add {$spec->action()} to");
        }

        return substr($text, 0, $end + 1) . "\n" . (new ActionRenderer())->method($spec) . substr($text, $end + 1);
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

        return str_replace("\r\n", "\n", file_get_contents($file));
    }
}
