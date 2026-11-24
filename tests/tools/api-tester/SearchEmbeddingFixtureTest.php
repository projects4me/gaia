<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\ApiTester;

use ApiTester\SearchEmbeddingFixture;
use Gaia\Libraries\Search\SemanticText;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the search fixture's stored embeddings (agaia-4896): the text and hash step with Gaia's SemanticText,
 * the batched embedding, the checks on the embedding service's answer, and that the committed data file still matches
 * the text rule.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchEmbeddingFixtureTest extends TestCase
{
    /**
     * @var SemanticText
     */
    private $semanticText;

    protected function setUp(): void
    {
        $this->semanticText = new SemanticText(array('subject', 'description'), SemanticText::DEFAULT_MAX_CHARS);
    }

    public function testDescribeBuildsTheSemanticTextAndItsHashWithGaiasRule()
    {
        $entries = $this->fixture($this->embedder())->describe(array(
            array('id' => 'a', 'subject' => 'Ledger  export', 'description' => '<p>Drops the <b>currency</b> &amp; total.</p>'),
        ));

        $text = "Ledger export\nDrops the currency & total.";
        $this->assertSame(array(array(
            'id' => 'a',
            'subject' => 'Ledger  export',
            'description' => '<p>Drops the <b>currency</b> &amp; total.</p>',
            'semanticText' => $text,
            'hash' => hash('sha256', $text),
        )), $entries);
    }

    public function testDescribeRejectsARecordWithoutASourceField()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no description');

        $this->fixture($this->embedder())->describe(array(array('id' => 'a', 'subject' => 'Ledger')));
    }

    public function testDescribeRejectsARecordWithNoSemanticText()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fixture record a has no semantic text');

        $this->fixture($this->embedder())->describe(array(array('id' => 'a', 'subject' => ' ', 'description' => '<p></p>')));
    }

    public function testBuildEmbedsInBatchesAndKeepsTheRecordOrder()
    {
        $calls = array();
        $records = array();
        for ($i = 0; $i < SearchEmbeddingFixture::BATCH + 1; $i++) {
            $records[] = array('id' => 'r' . $i, 'subject' => 'Subject ' . $i, 'description' => null);
        }

        $data = $this->fixture($this->embedder($calls))->build($records);

        $this->assertSame(array(SearchEmbeddingFixture::BATCH, 1), array_map('count', $calls));
        $this->assertSame('test-model', $data['model']);
        $this->assertSame(2, $data['dimensions']);
        $this->assertCount(SearchEmbeddingFixture::BATCH + 1, $data['records']);
        $this->assertSame('Subject 64', $data['records'][64]['semanticText']);
        $this->assertSame(array(64.0, 1.0), $data['records'][64]['embedding']);
    }

    /**
     * @dataProvider badAnswers
     */
    public function testBuildRejectsAnAnswerThatDoesNotFit(array $answer, $message)
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->fixture(function () use ($answer) {
            return $answer;
        })->build(array(
            array('id' => 'a', 'subject' => 'One', 'description' => null),
            array('id' => 'b', 'subject' => 'Two', 'description' => null),
        ));
    }

    public static function badAnswers()
    {
        return array(
            'too few vectors' => array(array('model' => 'm', 'embeddings' => array(array(1.0))), '1 vectors for 2 texts'),
            'no model' => array(array('embeddings' => array(array(1.0), array(2.0))), 'named no model'),
            'sizes differ' => array(array('model' => 'm', 'embeddings' => array(array(1.0), array(1.0, 2.0))), 'wrong size'),
            'not a number' => array(array('model' => 'm', 'embeddings' => array(array(1.0), array('x'))), 'not a number'),
        );
    }

    public function testTheCommittedDataFileMatchesTheTextRule()
    {
        $data = json_decode(file_get_contents(APP_PATH . '/tools/api-tester/fixtures/search-embeddings.json'), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data['records']);

        $models = array();
        require APP_PATH . '/app/metadata/model/Issue.php';
        $rule = SemanticText::forBlock($models['Issue']['search']);
        foreach ($data['records'] as $record) {
            $this->assertSame($rule->text($record), $record['semanticText'], "{$record['id']}: regenerate the data file");
            $this->assertSame(SemanticText::hashOf($record['semanticText']), $record['hash'], $record['id']);
            $this->assertCount($data['dimensions'], $record['embedding'], $record['id']);
        }
    }

    /**
     * @param callable $embed
     * @return SearchEmbeddingFixture
     */
    private function fixture(callable $embed)
    {
        $semanticText = $this->semanticText;

        return new SearchEmbeddingFixture(
            function (array $record) use ($semanticText) {
                return $semanticText->text($record);
            },
            function ($text) {
                return SemanticText::hashOf($text);
            },
            $embed
        );
    }

    /**
     * An embedder that answers each text with [its number, 1] and records the batches it was given.
     *
     * @param array $calls
     * @return callable
     */
    private function embedder(array &$calls = array())
    {
        return function (array $texts) use (&$calls) {
            $calls[] = $texts;

            return array(
                'model' => 'test-model',
                'embeddings' => array_map(function ($text) {
                    return array((float) preg_replace('/\D/', '', $text), 1.0);
                }, $texts),
            );
        };
    }
}
