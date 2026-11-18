<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\PhqlIdentifiers;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PhqlIdentifiers: Gaia writes columns as `[Model].[field]` so Phalcon's PHQL scanner keeps
 * names that start with a keyword such as "not" (agaia-4678).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PhqlIdentifiersTest extends TestCase
{
    /**
     * @var PhqlIdentifiers
     */
    protected $identifiers;

    protected function setUp(): void
    {
        $this->identifiers = new PhqlIdentifiers();
    }

    public function testWhereReferencesAreBracketed()
    {
        $this->assertSame(
            '(([Mention].[notified] = :q0:) AND ([Mention].[notes] LIKE :q1:)) OR ([project].[name] IS NULL)',
            $this->identifiers->bracket(
                '((Mention.notified = :q0:) AND (Mention.notes LIKE :q1:)) OR (project.name IS NULL)'
            )
        );
    }

    public function testCastAndInListKeepTheirShape()
    {
        $this->assertSame(
            '(CAST([Issue].[estimate] AS TEXT) LIKE :q0:) AND ([Issue].[id] IN(:q1:, :q2:))',
            $this->identifiers->bracket('(CAST(Issue.estimate AS TEXT) LIKE :q0:) AND (Issue.id IN(:q1:, :q2:))')
        );
    }

    public function testQuotedTextAndPlaceholdersAreLeftAlone()
    {
        $this->assertSame(
            "[Agent].[userId] = :userId: AND [Agent].[name] <> 'a.notb' AND [Agent].[purpose] <> \"x.y\"",
            $this->identifiers->bracket("Agent.userId = :userId: AND Agent.name <> 'a.notb' AND Agent.purpose <> \"x.y\"")
        );
    }

    public function testStarNumbersAndBracketedNamesAreLeftAlone()
    {
        $this->assertSame('members.*', $this->identifiers->bracket('members.*'));
        $this->assertSame('[Issue].[id] > 1.5', $this->identifiers->bracket('[Issue].[id] > 1.5'));
        $this->assertSame('', $this->identifiers->bracket(''));
        $this->assertNull($this->identifiers->bracket(null));
    }

    public function testSortTermsAreBracketedQualifiedOrBare()
    {
        $this->assertSame('[Mention].[notified] DESC', $this->identifiers->bracketTerms('Mention.notified DESC'));
        $this->assertSame('[notified] ASC', $this->identifiers->bracketTerms('notified ASC'));
        $this->assertSame('[notes],[Mention].[id] desc', $this->identifiers->bracketTerms('notes,Mention.id desc'));
        $this->assertSame('', $this->identifiers->bracketTerms(''));
        $this->assertNull($this->identifiers->bracketTerms(null));
    }

    public function testColumnsAreBracketedWithTheirAliases()
    {
        $this->assertSame(
            [
                '[Mention].[notified]',
                '[notes].[notice] AS [notes_notice]',
                'COUNT([Mention].[id]) AS [total]',
                'notes.*',
            ],
            $this->identifiers->bracketColumns([
                'Mention.notified',
                'notes.notice AS notes_notice',
                'COUNT(Mention.id) as total',
                'notes.*',
            ])
        );
    }

    public function testGroupByEntriesAreBracketed()
    {
        $this->assertSame(
            ['[Mention].[notified]', '[notes]'],
            $this->identifiers->bracketTerms(['Mention.notified', 'notes'])
        );
    }

    /**
     * The point of the class: Phalcon reads every bracketed name in full, including names starting with "not".
     */
    public function testPhalconParsesBracketedNamesStartingWithNot()
    {
        if (!class_exists(\Phalcon\Mvc\Model\Query\Lang::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }

        $columns = $this->identifiers->bracketColumns(['Mention.notified', 'notes.notice AS notes_notice']);
        $phql = 'SELECT ' . implode(', ', $columns) . ' FROM Mention'
            . ' WHERE ' . $this->identifiers->bracket('(Mention.notified = :q0:) AND (notes.nothing IS NULL)')
            . ' GROUP BY ' . $this->identifiers->bracketTerms('Mention.notes')
            . ' ORDER BY ' . $this->identifiers->bracketTerms('Mention.notifiedAt DESC, nothingness');

        $names = $this->namesIn(\Phalcon\Mvc\Model\Query\Lang::parsePHQL($phql));

        foreach (['notified', 'notice', 'notes_notice', 'nothing', 'notes', 'notifiedAt', 'nothingness'] as $name) {
            $this->assertContains($name, $names, "PHQL lost the name {$name}: {$phql}");
        }
        foreach (['ified', 'ice', 'es_notice', 'hing', 'es', 'ifiedAt', 'hingness'] as $stripped) {
            $this->assertNotContains($stripped, $names, "PHQL stripped a name to {$stripped}: {$phql}");
        }
    }

    /**
     * Every column name and alias in a parsed PHQL tree.
     *
     * @param  array $tree
     * @return array
     */
    private function namesIn(array $tree)
    {
        $names = [];
        array_walk_recursive($tree, function ($value, $key) use (&$names) {
            if (in_array($key, ['name', 'alias', 'column'], true) && is_string($value)) {
                $names[] = $value;
            }
        });
        return $names;
    }
}
