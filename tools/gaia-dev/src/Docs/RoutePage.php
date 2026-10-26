<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

use GaiaDev\Query\GraphReader;

/**
 * The route table, one section per resource.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RoutePage implements PageBuilder
{
    private const OTHER = 'other';

    /**
     * {@inheritdoc}
     */
    public function build(GraphReader $graph): array
    {
        $groups = [];
        foreach ($graph->nodes('route') as $route) {
            $actions = $graph->out($route, 'handled_by');
            $action = $actions ? $actions[0]['key'] : '';
            $group = $action !== '' ? explode('.', $action)[0] : self::OTHER;
            $groups[$group][] = [
                $route['attributes']['method'],
                $route['attributes']['path'],
                $route['attributes']['type'],
                $action !== '' ? $action : '-',
            ];
        }
        ksort($groups);

        $lines = Markdown::page('Route table', 'core/config/routes.php');
        foreach ($groups as $group => $rows) {
            sort($rows);
            $lines = array_merge(
                $lines,
                Markdown::section($group, Markdown::table(['Method', 'Path', 'Type', 'Action'], $rows))
            );
        }

        return ['routes.md' => Markdown::render($lines)];
    }
}
