<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * Turns flat translation rows into the nested document ember-intl reads.
 *
 * `['a.b.c' => 'Label']` becomes `['a' => ['b' => ['c' => 'Label']]]`, the shape of
 * Prometheus's translations/*.json. Paths are applied in sorted order, so the result
 * does not depend on row order. When paths collide (`a.b` is a label and `a.b.c` would
 * need it to be a group) the shorter path wins and the longer one is skipped.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TranslationTree
{
    /**
     * @param  array $labels Dotted path => label.
     * @return array
     */
    public static function build(array $labels)
    {
        ksort($labels, SORT_STRING);

        $tree = [];
        foreach ($labels as $path => $label) {
            self::place($tree, explode('.', (string) $path), $label);
        }

        return $tree;
    }

    /**
     * @param  array  $tree
     * @param  array  $segments
     * @param  string $label
     * @return void
     */
    private static function place(array &$tree, array $segments, $label)
    {
        if (in_array('', $segments, true)) {
            return;
        }

        $leaf = array_pop($segments);
        $node = &$tree;
        foreach ($segments as $segment) {
            if (!isset($node[$segment])) {
                $node[$segment] = [];
            } elseif (!is_array($node[$segment])) {
                return;
            }
            $node = &$node[$segment];
        }

        if (!isset($node[$leaf])) {
            $node[$leaf] = $label;
        }
    }
}
