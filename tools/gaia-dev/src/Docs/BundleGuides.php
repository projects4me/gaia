<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

/**
 * One guide per change bundle in docs/dev/bundles.md, with the exemplar PRs and
 * the golden files turned into links. The catalog stays the one place the
 * bundles are written; the guides only split it into short, anchored sections.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BundleGuides
{
    public const CATALOG = 'docs/dev/bundles.md';

    private const PULL_URL = 'https://github.com/projects4me/gaia/pull/';

    /**
     * Guides sit in docs/generated/bundles/, three levels below the repository root.
     */
    private const ROOT_LINK = '../../../';

    /**
     * @var string repository root
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return string[] path relative to docs/generated => page content (none without a catalog)
     */
    public function build(): array
    {
        $path = $this->root . '/' . self::CATALOG;
        if (!is_file($path)) {
            return [];
        }

        $pages = [];
        $index = Markdown::page('Change bundles', self::CATALOG);
        $index = array_merge($index, ['Each guide links its exemplar PRs and golden files. Ranking and cross-cutting checks: [docs/dev/bundles.md](' . self::ROOT_LINK . self::CATALOG . ').', '']);
        foreach ($this->bundles(str_replace("\r\n", "\n", file_get_contents($path))) as $letter => $bundle) {
            $lines = Markdown::page('Bundle ' . $letter . '. ' . $bundle['title'], self::CATALOG);
            foreach ($bundle['sections'] as $heading => $body) {
                $lines = array_merge($lines, Markdown::section($heading, array_map([$this, 'link'], $body)));
            }
            $pages['bundles/' . $letter . '.md'] = Markdown::render($lines);
            $index[] = '- [' . $letter . '. ' . $bundle['title'] . '](' . $letter . '.md)';
        }
        $pages['bundles/README.md'] = Markdown::render($index);

        return $pages;
    }

    /**
     * @param string $catalog
     * @return array[] letter => ['title' => string, 'sections' => heading => lines]
     */
    private function bundles(string $catalog): array
    {
        $bundles = [];
        $letter = null;
        $heading = 'Overview';
        foreach (explode("\n", $catalog) as $line) {
            if (preg_match('/^## ([A-Z])\. (.+)$/', $line, $match)) {
                $letter = $match[1];
                $heading = 'Overview';
                $bundles[$letter] = ['title' => $match[2], 'sections' => []];
                continue;
            }
            if ($letter === null) {
                continue;
            }
            if (strpos($line, '## ') === 0 || trim($line) === '---') {
                $letter = strpos($line, '## ') === 0 ? null : $letter;
                $heading = 'Overview';
                continue;
            }
            if (preg_match('/^\*\*([^*]+?):?\*\*:?\s*(.*)$/', $line, $match)) {
                $heading = $match[1];
                $line = $match[2];
                if ($line === '') {
                    continue;
                }
            }
            $bundles[$letter]['sections'][$heading][] = $line;
        }

        return array_map([$this, 'trim'], $bundles);
    }

    /**
     * @param array $bundle
     * @return array with blank lines trimmed off each section and empty sections dropped
     */
    private function trim(array $bundle): array
    {
        $sections = [];
        foreach ($bundle['sections'] as $heading => $lines) {
            $text = trim(implode("\n", $lines));
            if ($text !== '') {
                $sections[$heading] = explode("\n", $text);
            }
        }
        $bundle['sections'] = $sections;

        return $bundle;
    }

    /**
     * Links `path` tokens that exist in the repository and #123 pull request numbers.
     *
     * @param string $line
     * @return string
     */
    private function link(string $line): string
    {
        $line = preg_replace_callback('/`([A-Za-z0-9_.\/-]+\.[a-z]+)`/', function ($match) {
            return is_file($this->root . '/' . $match[1])
                ? '[`' . $match[1] . '`](' . self::ROOT_LINK . $match[1] . ')'
                : $match[0];
        }, $line);

        return preg_replace('/(?<![\w\/&\[])#(\d{3})\b(?!\])/', '[#$1](' . self::PULL_URL . '$1)', $line);
    }
}
