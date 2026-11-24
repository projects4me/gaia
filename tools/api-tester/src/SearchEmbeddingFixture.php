<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace ApiTester;

/**
 * Builds the stored embeddings of the search fixture (agaia-4896): for each fixture record, its semantic text, the
 * hash of that text and the embedding of that text, plus the model that made them. The search seed loads the result,
 * so the meaning-search cases do not wait for Ares to embed the seed.
 *
 * The rule for the text and its hash and the call to the embedding service are handed in, so this class stays free
 * of the application: the generator (bin/generate-search-embeddings.php) passes the application's own rule.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package ApiTester
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchEmbeddingFixture
{
    /**
     * Texts embedded per call (the embedding service takes at most 64).
     */
    public const BATCH = 64;

    /**
     * The fields of a record that the seed checks before it loads the record's embedding.
     */
    public const SOURCE_FIELDS = ['id', 'subject', 'description'];

    /**
     * @var callable function (array $record): string, the record's semantic text
     */
    private $text;

    /**
     * @var callable function (string $text): string, the hash of a semantic text
     */
    private $hash;

    /**
     * @var callable function (string[] $texts): array, ['model' => string, 'embeddings' => float[][]]
     */
    private $embed;

    /**
     * @param callable $text The record's semantic text
     * @param callable $hash The hash of a semantic text
     * @param callable $embed Embeds document texts: ['model' => ..., 'embeddings' => [...]], one vector per text in order
     */
    public function __construct(callable $text, callable $hash, callable $embed)
    {
        $this->text = $text;
        $this->hash = $hash;
        $this->embed = $embed;
    }

    /**
     * This function returns the semantic text and hash of each record, in order, without embedding them.
     *
     * @param array[] $records Each with SOURCE_FIELDS
     * @return array[] Each record's SOURCE_FIELDS with semanticText and hash
     * @throws \RuntimeException When a record lacks a source field or has no semantic text
     */
    public function describe(array $records)
    {
        $entries = [];
        foreach ($records as $record) {
            $entry = [];
            foreach (self::SOURCE_FIELDS as $field) {
                if (!is_array($record) || !array_key_exists($field, $record)) {
                    throw new \RuntimeException("A fixture record has no {$field}.");
                }
                $entry[$field] = $record[$field];
            }
            $text = (string) call_user_func($this->text, $record);
            if ($text === '') {
                throw new \RuntimeException("Fixture record {$entry['id']} has no semantic text to embed.");
            }
            $entry['semanticText'] = $text;
            $entry['hash'] = (string) call_user_func($this->hash, $text);
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * This function returns the data file: the model, the dimensions and each record with its text, hash and embedding.
     *
     * @param array[] $records Each with SOURCE_FIELDS
     * @return array
     * @throws \RuntimeException When the embedding service answers with another model, size or number of vectors
     */
    public function build(array $records)
    {
        $entries = $this->describe($records);
        $model = null;
        $dimensions = null;
        foreach (array_chunk(array_keys($entries), self::BATCH) as $keys) {
            $texts = [];
            foreach ($keys as $key) {
                $texts[] = $entries[$key]['semanticText'];
            }
            $answer = call_user_func($this->embed, $texts);
            $vectors = isset($answer['embeddings']) && is_array($answer['embeddings']) ? array_values($answer['embeddings']) : [];
            if (count($vectors) !== count($texts)) {
                throw new \RuntimeException('The embedding service returned ' . count($vectors) . ' vectors for ' . count($texts) . ' texts.');
            }
            $answerModel = isset($answer['model']) ? (string) $answer['model'] : '';
            if ($answerModel === '' || ($model !== null && $answerModel !== $model)) {
                throw new \RuntimeException("The embedding service named no model, or changed it to '{$answerModel}' mid-run.");
            }
            $model = $answerModel;
            foreach ($keys as $index => $key) {
                $vector = $this->vector($vectors[$index], $dimensions);
                $dimensions = count($vector);
                $entries[$key]['embedding'] = $vector;
            }
        }

        return [
            'description' => 'Stored embeddings of the search fixture (harness/seed-search.sql), loaded by harness/seed-search.sh. '
                . 'Regenerate with harness/generate-search-embeddings.sh; do not edit by hand.',
            'model' => $model,
            'dimensions' => $dimensions,
            'records' => $entries,
        ];
    }

    /**
     * This function checks one vector: finite numbers, as many as the ones before it.
     *
     * @param mixed $vector
     * @param int|null $dimensions The size of the vectors so far, null for the first
     * @return float[]
     * @throws \RuntimeException
     */
    private function vector($vector, $dimensions)
    {
        if (!is_array($vector) || $vector === [] || ($dimensions !== null && count($vector) !== $dimensions)) {
            throw new \RuntimeException('The embedding service returned a vector of the wrong size.');
        }
        $numbers = [];
        foreach ($vector as $number) {
            if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number)) {
                throw new \RuntimeException('The embedding service returned a vector with a value that is not a number.');
            }
            $numbers[] = (float) $number;
        }

        return $numbers;
    }
}
