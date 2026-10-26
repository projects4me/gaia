<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

use GaiaDev\Query\GraphReader;

/**
 * Runs the page builders and adds the index: the complete set of files under
 * docs/generated (apart from graph.json), keyed by path.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DocsBuilder
{
    /**
     * @var string repository root
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = $root;
    }

    /**
     * @param GraphReader $graph
     * @return string[] path relative to docs/generated => content, sorted by path
     */
    public function build(GraphReader $graph): array
    {
        $pages = array_merge(
            (new ModelPages())->build($graph),
            (new RoutePage())->build($graph),
            (new AclMatrixPage())->build($graph),
            (new VocabularyPages())->build($graph),
            (new BundleGuides($this->root))->build()
        );
        $pages['README.md'] = $this->index(array_keys($pages));
        ksort($pages);

        return $pages;
    }

    /**
     * @param string[] $paths
     * @return string
     */
    private function index(array $paths): string
    {
        $models = array_filter($paths, function ($path) {
            return strpos($path, 'models/') === 0;
        });
        $lines = Markdown::page('Generated docs', 'docs/generated/graph.json');
        $lines = array_merge($lines, Markdown::section('Pages', [
            '- [Route table](routes.md): every route with its action',
            '- [ACL matrix](acl-matrix.md): resource by auth profile',
            '- [Assertion vocabulary](assertions.md): the `expects` keys with one real case each',
            '- [Auth profiles](profiles.md): role, grants and case counts',
            '- [Change bundles](bundles/README.md): the touchpoints, exemplar PRs and golden files per kind of change',
            '- Models: `models/<Model>.md`, ' . count($models) . ' pages (`gaia-dev model <Name>` prints the same)',
        ]));
        $lines = array_merge($lines, Markdown::section('Rules', [
            '- Do not edit these files. Change the source, then run `php tools/gaia-dev/bin/gaia-dev graph export`'
                . ' and `php tools/gaia-dev/bin/gaia-dev docs generate`.',
            '- CI fails when either is stale (`graph export --check`, `docs generate --check`).',
            '- Every section has a stable anchor (`<a id="...">`) and at most ' . Markdown::SECTION_LINES . ' lines.',
            '- Hand-written reasons live in `docs/architecture/*/decisions.md`; `gaia-dev` tests check what they cite.',
        ]));

        return Markdown::render($lines);
    }
}
