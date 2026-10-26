<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Docs\CitationChecker;
use PHPUnit\Framework\TestCase;

/**
 * Drift test for the hand-written decisions docs: every file, class and method
 * they cite in backticks must still exist (agaia-4132).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DecisionsCitationsTest extends TestCase
{
    private const FIXTURE_ROOT = __DIR__ . '/fixtures/citations';

    /**
     * @return string[][] doc path
     */
    public function decisionsDocs(): array
    {
        $docs = [];
        foreach (glob(dirname(__DIR__, 3) . '/docs/architecture/*/decisions.md') as $path) {
            $docs[] = [substr($path, strlen(dirname(__DIR__, 3)) + 1)];
        }

        return $docs;
    }

    /**
     * @dataProvider decisionsDocs
     * @param string $document
     */
    public function testDecisionsCiteCodeThatExists(string $document): void
    {
        $this->assertSame([], (new CitationChecker(dirname(__DIR__, 3)))->missing($document));
    }

    public function testReportsEveryKindOfBrokenCitation(): void
    {
        $missing = (new CitationChecker(self::FIXTURE_ROOT))->missing('doc.md');

        $this->assertSame([
            '`app/Gone.php`: no such file or directory',
            '`Widget::vanished()`: Widget has no vanished',
            '`Ghost::run`: no class, interface or trait with that name',
            '`Missing\Namespaced`: no class, interface or trait with that name',
            '`GhostService`: no class, interface or trait with that name',
            '`removedAction()`: no method with that name',
            '`getRemoved`: no method with that name',
        ], $missing);
    }
}
