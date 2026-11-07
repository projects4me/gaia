<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\WhereTokenizer;
use Gaia\Exception\BadRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WhereTokenizer: quote-aware leaves, splitting and unquoting of where values (agaia-4303).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WhereTokenizerTest extends TestCase
{
    /**
     * @var WhereTokenizer
     */
    protected $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new WhereTokenizer();
    }

    public function testLeavesAreInnermostGroupsWithOffsets()
    {
        $leaves = $this->tokenizer->leaves('((A.a : 1) OR (A.b : 2))');

        $this->assertSame(
            [['text' => '(A.a : 1)', 'offset' => 1], ['text' => '(A.b : 2)', 'offset' => 14]],
            $leaves
        );
    }

    public function testBracketsInsideQuotesDoNotSplitALeaf()
    {
        $leaves = $this->tokenizer->leaves("(A.a CONTAINS 'fix (urgent)')");

        $this->assertSame([['text' => "(A.a CONTAINS 'fix (urgent)')", 'offset' => 0]], $leaves);
    }

    public function testApostropheInsideAnUnquotedValueIsPlainText()
    {
        $leaves = $this->tokenizer->leaves("((A.a : O'Brien) AND (A.b : x))");

        $this->assertCount(2, $leaves);
    }

    public function testQuoteThatNeverClosesIsPlainText()
    {
        $leaves = $this->tokenizer->leaves("((A.a : x' OR '1'='1) AND (A.b : 'abc))");

        $this->assertSame(
            [['text' => "(A.a : x' OR '1'='1)", 'offset' => 1], ['text' => "(A.b : 'abc)", 'offset' => 26]],
            $leaves
        );
    }

    public function testBlankGroupsAreNotLeaves()
    {
        $this->assertSame([['text' => '(A.a : 1)', 'offset' => 3]], $this->tokenizer->leaves('() (A.a : 1)'));
    }

    /**
     * @dataProvider malformed
     */
    public function testMalformedWhereIsABadRequest($where)
    {
        $this->expectException(BadRequest::class);

        $this->tokenizer->leaves($where);
    }

    public function malformed()
    {
        return [
            'missing close' => ['((A.a : 1)'],
            'missing open' => ['(A.a : 1))'],
            'close before open' => [')A.a : 1('],
            'bracket only balanced by a quoted one' => ["(A.a : 'x)'"],
        ];
    }

    public function testSplitIgnoresSeparatorsInsideQuotes()
    {
        $this->assertSame(
            ["'a, b'", "'c'", 'd'],
            $this->tokenizer->split("'a, b', 'c',d", ',')
        );
        $this->assertSame(["'x AND y'", "'z'"], $this->tokenizer->split("'x AND y' AND 'z'", ' AND '));
    }

    /**
     * @dataProvider quotedTokens
     */
    public function testUnquote($token, $expected)
    {
        $this->assertSame($expected, $this->tokenizer->unquote($token));
    }

    public function quotedTokens()
    {
        return [
            'doubled apostrophe' => ["'O''Brien'", "O'Brien"],
            'legacy escaped apostrophe' => ["'O\\'Brien'", "O'Brien"],
            'lone apostrophe inside' => ["'don't'", "don't"],
            'empty' => ["''", ''],
            'brackets and commas' => ["'a (b), c'", 'a (b), c'],
            'unquoted' => ['abc', null],
            'two quoted values' => ["'a','b'", null],
            'quoted then text' => ["'a' b", null],
            'not closed' => ["'abc", null],
        ];
    }
}
