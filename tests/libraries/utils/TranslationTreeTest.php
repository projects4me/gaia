<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\TranslationTree;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for building the nested ember-intl document from dotted paths.
 */
class TranslationTreeTest extends TestCase
{
    public function testDottedPathsBecomeTheShapeOfPrometheusTranslationFiles()
    {
        $tree = TranslationTree::build([
            'views.app.issue.lists.status.new' => 'New',
            'views.app.issue.lists.status.in_progress' => 'In Progress',
            'views.app.issue.title' => 'Issues',
            'global.ok' => 'OK',
        ]);

        $this->assertSame([
            'global' => ['ok' => 'OK'],
            'views' => ['app' => ['issue' => [
                'lists' => ['status' => ['in_progress' => 'In Progress', 'new' => 'New']],
                'title' => 'Issues',
            ]]],
        ], $tree);
    }

    public function testRowOrderDoesNotChangeTheResult()
    {
        $labels = ['a.b' => 'B', 'a.c.d' => 'D', 'e' => 'E'];

        $this->assertSame(TranslationTree::build($labels), TranslationTree::build(array_reverse($labels, true)));
    }

    public function testWhenPathsCollideTheLabelOnTheShorterPathWins()
    {
        $tree = TranslationTree::build([
            'a.b' => 'label',
            'a.b.c' => 'under a label',
            'x.y.z' => 'Z',
            'x.y' => 'over a group',
        ]);

        $this->assertSame(['a' => ['b' => 'label'], 'x' => ['y' => 'over a group']], $tree);
    }

    public function testPathsWithEmptySegmentsAreSkipped()
    {
        $this->assertSame(['ok' => 'OK'], TranslationTree::build(['.a' => '1', 'a.' => '2', 'a..b' => '3', '' => '4', 'ok' => 'OK']));
    }

    public function testNoLabelsGiveAnEmptyTree()
    {
        $this->assertSame([], TranslationTree::build([]));
    }
}
