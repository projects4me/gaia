<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * A validated embedding write from the Ares service (D-072): the record id, the embedding and the hash of the
 * semantic text it was made from. The body carries searchEmbedding and searchEmbeddingHash and nothing else.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EmbeddingWrite
{
    /**
     * Error for a body that is not exactly the embedding and its hash.
     */
    public const FIELDS_ERROR = 'Send searchEmbedding and searchEmbeddingHash alone.';

    /**
     * Error for a missing record id, or one that differs from the URL's.
     */
    public const ID_ERROR = 'The record id is required in the body (data.id), the same as the URL\'s.';

    /**
     * Error for an embedding that is not a list of numbers of the right length.
     */
    public const EMBEDDING_ERROR = 'searchEmbedding must be a list of ' . SearchBlock::EMBEDDING_DIMENSIONS . ' numbers.';

    /**
     * Error for a hash that is not a SemanticText hash.
     */
    public const HASH_ERROR = 'searchEmbeddingHash must be the 64 character hash of the semantic text.';

    /**
     * A SemanticText hash: sha256 in lower case hex.
     *
     * @var string
     */
    private const HASH_PATTERN = '/^[0-9a-f]{64}\z/';

    /**
     * @var string
     */
    private $id;

    /**
     * @var float[]
     */
    private $embedding;

    /**
     * @var string
     */
    private $hash;

    /**
     * @param string $id
     * @param float[] $embedding
     * @param string $hash
     */
    private function __construct($id, array $embedding, $hash)
    {
        $this->id = $id;
        $this->embedding = $embedding;
        $this->hash = $hash;
    }

    /**
     * This function validates the attributes of a PATCH and returns the write.
     *
     * @param array $attributes The body's attributes; an id there must be the URL's
     * @param string|null $urlId The id in the URL, if any
     * @return EmbeddingWrite
     * @throws \Gaia\Exception\BadRequest
     */
    public static function fromAttributes(array $attributes, $urlId = null)
    {
        $urlId = $urlId === null ? '' : (string) $urlId;
        $bodyId = array_key_exists('id', $attributes) ? $attributes['id'] : null;
        if ($bodyId !== null && (!is_string($bodyId) || ($urlId !== '' && $bodyId !== $urlId))) {
            throw new \Gaia\Exception\BadRequest(self::ID_ERROR);
        }
        $id = $bodyId !== null ? $bodyId : $urlId;
        if ($id === '') {
            throw new \Gaia\Exception\BadRequest(self::ID_ERROR);
        }

        unset($attributes['id']);
        $fields = array_keys($attributes);
        sort($fields);
        $expected = array(SearchBlock::EMBEDDING, SearchBlock::EMBEDDING_HASH);
        sort($expected);
        if ($fields !== $expected) {
            throw new \Gaia\Exception\BadRequest(self::FIELDS_ERROR);
        }

        $hash = $attributes[SearchBlock::EMBEDDING_HASH];
        if (!is_string($hash) || !preg_match(self::HASH_PATTERN, $hash)) {
            throw new \Gaia\Exception\BadRequest(self::HASH_ERROR);
        }

        return new self($id, self::numbers($attributes[SearchBlock::EMBEDDING]), $hash);
    }

    /**
     * @return string
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * @return float[]
     */
    public function embedding()
    {
        return $this->embedding;
    }

    /**
     * @return string
     */
    public function hash()
    {
        return $this->hash;
    }

    /**
     * This function checks the embedding: a list of EMBEDDING_DIMENSIONS finite numbers.
     *
     * @param mixed $value
     * @return float[]
     * @throws \Gaia\Exception\BadRequest
     */
    private static function numbers($value)
    {
        if (
            !is_array($value) || count($value) !== SearchBlock::EMBEDDING_DIMENSIONS
            || array_keys($value) !== range(0, SearchBlock::EMBEDDING_DIMENSIONS - 1)
        ) {
            throw new \Gaia\Exception\BadRequest(self::EMBEDDING_ERROR);
        }

        $numbers = array();
        foreach ($value as $number) {
            if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number)) {
                throw new \Gaia\Exception\BadRequest(self::EMBEDDING_ERROR);
            }
            $numbers[] = (float) $number;
        }

        return $numbers;
    }
}
