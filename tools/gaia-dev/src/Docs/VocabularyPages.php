<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

use GaiaDev\Query\AssertionsQuery;
use GaiaDev\Query\GraphReader;
use GaiaDev\Query\ProfilesQuery;

/**
 * The assertion vocabulary with one real example each, and the auth profiles.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class VocabularyPages implements PageBuilder
{
    /**
     * {@inheritdoc}
     */
    public function build(GraphReader $graph): array
    {
        return [
            'assertions.md' => $this->page(
                'Assertion vocabulary',
                'tools/api-tester/src/Asserter.php',
                (new AssertionsQuery())->run($graph, [], []),
                'assertions'
            ),
            'profiles.md' => $this->page(
                'Auth profiles',
                'tools/api-tester/fixtures/default.json',
                (new ProfilesQuery())->run($graph, [], []),
                'profiles'
            ),
        ];
    }

    /**
     * @param string $title
     * @param string $source
     * @param array $answer a query answer
     * @param string $label the answer key holding the lines
     * @return string
     */
    private function page(string $title, string $source, array $answer, string $label): string
    {
        $lines = Markdown::page($title, $source);
        $lines[] = $answer['title'];
        $lines[] = '';

        $bullets = array_map(function ($line) {
            return '- ' . $line;
        }, $answer[$label]);

        return Markdown::render(array_merge($lines, Markdown::section(ucfirst($label), $bullets)));
    }
}
