<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Search\SemanticText;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the canonical semantic text of a record and its hash.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SemanticTextTest extends TestCase
{
    /**
     * @var string|false
     */
    private $configuredMaxChars;

    protected function setUp(): void
    {
        $this->configuredMaxChars = getenv('SEARCH_EMBED_MAX_CHARS');
        putenv('SEARCH_EMBED_MAX_CHARS');
    }

    protected function tearDown(): void
    {
        putenv($this->configuredMaxChars === false
            ? 'SEARCH_EMBED_MAX_CHARS'
            : 'SEARCH_EMBED_MAX_CHARS=' . $this->configuredMaxChars);
    }

    public function testFieldsAreJoinedInTheirOrder()
    {
        $text = new SemanticText(['subject', 'description']);

        $this->assertSame(
            "Login fails\nThe form returns 500",
            $text->text(['description' => 'The form returns 500', 'subject' => 'Login fails', 'status' => 'new'])
        );
    }

    public function testHtmlIsStrippedAndEntitiesDecoded()
    {
        $text = new SemanticText(['description']);

        $this->assertSame(
            'Steps Open the page Click Save & wait "quoted" a < b',
            $text->text(['description' => '<h1>Steps</h1><ol><li>Open the page</li><li>Click <b>Save</b>'
                . ' &amp; wait</li></ol><!-- note --><script>alert(1)</script><style>p{}</style>'
                . '&quot;quoted&quot; a &lt; b'])
        );
    }

    public function testWhitespaceIsCollapsed()
    {
        $text = new SemanticText(['subject', 'description']);

        $this->assertSame(
            "Two words\nline one line two",
            $text->text(['subject' => "  Two \t words&nbsp; ", 'description' => "line one\r\n\r\n  line two\n"])
        );
    }

    public function testNullAndEmptyFieldsAreLeftOut()
    {
        $text = new SemanticText(['subject', 'summary', 'description']);

        $this->assertSame('Body', $text->text(['subject' => null, 'summary' => ' <p></p> ', 'description' => 'Body']));
        $this->assertSame('', $text->text([]));
        $this->assertSame(hash('sha256', ''), $text->hash([]));
    }

    public function testAModelsPropertiesAreRead()
    {
        $record = new \stdClass();
        $record->subject = 'Subject';
        $record->description = 7;

        $this->assertSame("Subject\n7", (new SemanticText(['subject', 'description']))->text($record));
    }

    public function testTheTextIsCutToTheMaximumCharacters()
    {
        $text = new SemanticText(['subject', 'description'], 8);

        // Characters, not bytes: ä counts once.
        $this->assertSame("Häll\nmor", $text->text(['subject' => 'Häll', 'description' => 'more text']));
        // A cut that ends in a space leaves no trailing space.
        $this->assertSame("ab\ncd", (new SemanticText(['subject', 'description'], 6))->text(['subject' => 'ab', 'description' => 'cd ef']));
    }

    public function testTheMaximumComesFromTheEnvironment()
    {
        $this->assertSame(SemanticText::DEFAULT_MAX_CHARS, SemanticText::configuredMaxChars());
        $this->assertSame(4000, SemanticText::DEFAULT_MAX_CHARS);

        putenv('SEARCH_EMBED_MAX_CHARS=5');
        $this->assertSame('abcde', (new SemanticText(['subject']))->text(['subject' => 'abcdefgh']));

        putenv('SEARCH_EMBED_MAX_CHARS=none');
        $this->assertSame(SemanticText::DEFAULT_MAX_CHARS, SemanticText::configuredMaxChars());
    }

    public function testTheHashIsTheSha256OfTheText()
    {
        $text = new SemanticText(['subject', 'description']);
        $record = ['subject' => 'Login fails', 'description' => '<p>The form returns 500</p>'];

        $this->assertSame(hash('sha256', "Login fails\nThe form returns 500"), $text->hash($record));
        $this->assertSame(64, strlen($text->hash($record)));
        $this->assertSame(SemanticText::hashOf($text->text($record)), $text->hash($record));
    }

    public function testTheSameValuesGiveTheSameHash()
    {
        $text = new SemanticText(['subject', 'description']);

        $this->assertSame(
            $text->hash(['subject' => 'Login fails', 'description' => '<p>The form   returns 500</p>']),
            (new SemanticText(['subject', 'description']))->hash(
                ['description' => "The form returns\n500", 'subject' => ' Login  fails ']
            )
        );
        $this->assertNotSame(
            $text->hash(['subject' => 'Login fails', 'description' => 'x']),
            $text->hash(['subject' => 'x', 'description' => 'Login fails'])
        );
    }

    public function testForBlockUsesTheSemanticFields()
    {
        $text = SemanticText::forBlock(['semantic' => ['description', 'subject']]);

        $this->assertSame("B\nA", $text->text(['subject' => 'A', 'description' => 'B']));
    }

    public function testInvalidUtf8BecomesTheReplacementCharacterWhateverTheSetting()
    {
        $text = new SemanticText(['subject']);
        $original = mb_substitute_character();
        try {
            foreach (['none', 0x3F] as $setting) {
                mb_substitute_character($setting);
                $this->assertSame("ok \u{FFFD} done", $text->text(['subject' => "ok \xC3 done"]));
                $this->assertSame($setting, mb_substitute_character());
            }
        } finally {
            mb_substitute_character($original);
        }
    }

    public function testAScriptOpenerInsideACommentDoesNotHideText()
    {
        $text = new SemanticText(['subject']);

        $this->assertSame(
            'Visible a &lt; b',
            $text->text(['subject' => '<!-- <script> -->Visible<script>ignored</script> a &amp;lt; b'])
        );
    }

    public function testFloatsReadTheSameInEveryLocale()
    {
        $text = new SemanticText(['subject']);
        $locale = setlocale(LC_NUMERIC, '0');
        try {
            setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'fr_FR.UTF-8', 'fr_FR');
            $this->assertSame('1.5', $text->text(['subject' => 1.5]));
            $this->assertSame('0.3', $text->text(['subject' => 0.1 + 0.2]));
            $this->assertSame('2', $text->text(['subject' => 2.0]));
        } finally {
            setlocale(LC_NUMERIC, $locale);
        }
    }

    public function testAPcreFailureFailsInsteadOfDroppingTheField()
    {
        $text = new SemanticText(['subject']);
        $limit = ini_get('pcre.backtrack_limit');
        $jit = ini_get('pcre.jit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '10');
        try {
            $this->expectException(\Gaia\Exception\Exception::class);
            $this->expectExceptionMessage('Semantic text could not be built');
            $text->text(['subject' => '<script>' . str_repeat('x', 1000) . '</script> visible']);
        } finally {
            ini_set('pcre.backtrack_limit', $limit);
            ini_set('pcre.jit', $jit);
        }
    }

    public function testAMaximumBelowOneIsRejected()
    {
        $this->expectException(\Gaia\Exception\Exception::class);

        new SemanticText(['subject'], 0);
    }
}
