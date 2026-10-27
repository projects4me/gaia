<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Check;

use GaiaDev\Query\GraphReader;
use GaiaDev\Scaffold\ActionSpec;

/**
 * `gaia-dev check action`: every action override the scaffolder wrote (tagged `@gaia-dev action <verb>`) is
 * still in its controller, its standard route is in the graph, its permission has a profile rule, and it has
 * at least one acl and one client case. Actions written by hand carry no tag and are not checked.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Check
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActionCheck
{
    private const RULES = ['grants', 'grants_members', 'denies'];

    /**
     * @var string
     */
    private $root;

    /**
     * @var GraphReader
     */
    private $graph;

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
     * @return string[] what is missing, one line each
     */
    public function problems(): array
    {
        $problems = [];
        foreach (glob($this->root . '/' . ActionSpec::CONTROLLERS . '/*Controller.php') ?: [] as $file) {
            $text = file_get_contents($file);
            $model = basename($file, 'Controller.php');
            preg_match_all('/' . preg_quote(ActionSpec::MARKER, '/') . '\s+(\w+)\s*\n(?:\s*\*[^\n]*\n)*?\s*\*\/\s*\n\s*public function (\w+)\(/', $text, $found, PREG_SET_ORDER);
            foreach ($found as [, $verb, $function]) {
                if (!isset(ActionSpec::VERBS[$verb]) || ActionSpec::VERBS[$verb][0] !== $function) {
                    $problems[] = "{$model}Controller::{$function} is tagged '{$verb}' but is not that verb's standard action";
                    continue;
                }
                $problems = array_merge($problems, $this->problemsOf(new ActionSpec($model, $verb, [])));
            }
        }

        return $problems;
    }

    /**
     * @param ActionSpec $spec
     * @return string[]
     */
    private function problemsOf(ActionSpec $spec): array
    {
        $label = "{$spec->model}Controller::{$spec->action()}";
        $route = $spec->method() . ' ' . $spec->path();
        if ($this->graph->node('route', $route) === null) {
            return ["{$label}: route {$route} is not in the graph (run graph export)"];
        }
        $permission = $this->graph->node('permission', $spec->permission());
        if ($permission === null) {
            return ["{$label}: permission {$spec->permission()} does not exist"];
        }

        $problems = [];
        if (!$this->hasRule($spec->permission())) {
            $problems[] = "{$label}: no profile has a rule on {$spec->permission()}";
        }
        foreach (['acl', 'client'] as $catalog) {
            if (!$this->hasCase($spec, $catalog)) {
                $problems[] = "{$label}: no enabled {$catalog} case for {$spec->method()} on {$spec->resource()}";
            }
        }

        return $problems;
    }

    /**
     * @param string $permission
     * @return bool whether a profile besides default grants, grants for members or denies it
     */
    private function hasRule(string $permission): bool
    {
        foreach ($this->graph->nodes('profile') as $key => $profile) {
            if ($key === 'default' || $key === 'anonymous') {
                continue;
            }
            foreach (self::RULES as $edge) {
                foreach ($this->graph->out($profile, $edge) as $target) {
                    if ($target['key'] === $permission) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param ActionSpec $spec
     * @param string $catalog
     * @return bool whether an enabled case of the catalog calls the verb's method on the resource
     */
    private function hasCase(ActionSpec $spec, string $catalog): bool
    {
        foreach ($this->graph->nodes('case') as $case) {
            $attributes = $case['attributes'];
            if ($attributes['catalog'] === $catalog && $attributes['enabled'] && $attributes['resource'] === $spec->resource()
                && $attributes['method'] === $spec->method()) {
                return true;
            }
        }

        return false;
    }
}
