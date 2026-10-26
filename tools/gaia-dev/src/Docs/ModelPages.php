<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

use GaiaDev\Query\GraphReader;
use GaiaDev\Query\ModelQuery;

/**
 * One page per model, from the same answer as `gaia-dev model <Name>`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelPages implements PageBuilder
{
    /**
     * {@inheritdoc}
     */
    public function build(GraphReader $graph): array
    {
        $query = new ModelQuery();
        $pages = [];
        foreach (array_keys($graph->nodes('model')) as $name) {
            $answer = $query->run($graph, [$name], []);
            $lines = Markdown::page($answer['title'], 'the model metadata, routes, ACL and api-tester catalogs');
            $summary = [];
            foreach ($answer as $label => $value) {
                if ($label === 'title') {
                    continue;
                }
                if (is_array($value)) {
                    $lines = array_merge($lines, Markdown::section(ucfirst($label), $this->bullets($value)));
                } else {
                    $summary[] = '- ' . $label . ': ' . $value;
                }
            }
            $lines = array_merge($lines, Markdown::section('Summary', $summary));
            $pages['models/' . $name . '.md'] = Markdown::render($lines);
        }

        return $pages;
    }

    /**
     * @param string[] $values
     * @return string[]
     */
    private function bullets(array $values): array
    {
        return $values ? array_map(function ($value) {
            return '- ' . $value;
        }, $values) : ['- none'];
    }
}
