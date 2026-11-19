<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Stores the embedding the Ares service made for a record, on the searchable model's own table (D-072). Events reach
 * Ares out of order, so an embedding is stored only if it was made from the record's current semantic text: in one
 * transaction the row is locked and its semantic hash compared with the one the embedding was made from. The write
 * is a bound UPDATE of the embedding columns alone: no behaviours, no audit, no dateModified and no live event, since
 * the record itself did not change.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchEmbeddings
{
    /**
     * Error for a collection PATCH by the service.
     */
    public const ONE_RECORD_ERROR = 'Embeddings are written one record at a time.';

    /**
     * Error for a record that is missing or deleted.
     */
    public const NOT_FOUND_ERROR = 'The record does not exist.';

    /**
     * Error for an embedding made from a text that is no longer the record's.
     */
    public const STALE_ERROR = 'searchEmbeddingHash is not the hash of the record\'s current semantic text.';

    /**
     * Table and column names the queries take; they come from model metadata, never from the request.
     *
     * @var string
     */
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*\z/';

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface|null
     */
    private $db;

    /**
     * @param \Phalcon\Db\Adapter\AdapterInterface|null $db Null uses the shared connection
     */
    public function __construct($db = null)
    {
        $this->db = $db;
    }

    /**
     * This function stores the embedding, its hash and the time it was stored, if the hash is the record's current
     * semantic hash. deleted is smallint on some tables and boolean on others, hence the cast.
     *
     * @param string $table
     * @param EmbeddingWrite $write
     * @return string The time the embedding was stored (UTC, Y-m-d H:i:s)
     * @throws \Gaia\Exception\ResourceNotFound When the record is missing or deleted
     * @throws \Gaia\Exception\Conflict When the hash is not the current one; nothing is written
     */
    public function store($table, EmbeddingWrite $write)
    {
        $db = $this->db();
        $table = $this->quote($table);
        $db->begin();
        try {
            $row = $db->fetchOne(
                'SELECT ' . $this->quote(SearchBlock::SEMANTIC_HASH) . ' AS "hash" FROM ' . $table
                . ' WHERE "id" = ? AND "deleted"::int = 0 FOR UPDATE',
                \Phalcon\Db\Enum::FETCH_ASSOC,
                array($write->id())
            );
            if (!is_array($row)) {
                throw new \Gaia\Exception\ResourceNotFound(self::NOT_FOUND_ERROR);
            }
            if (!is_string($row['hash']) || !hash_equals($row['hash'], $write->hash())) {
                throw new \Gaia\Exception\Conflict(self::STALE_ERROR);
            }

            $embeddedAt = gmdate('Y-m-d H:i:s');
            $db->execute(
                'UPDATE ' . $table . ' SET ' . $this->quote(SearchBlock::EMBEDDING) . ' = CAST(? AS vector), '
                . $this->quote(SearchBlock::EMBEDDING_HASH) . ' = ?, '
                . $this->quote(SearchBlock::EMBEDDED_AT) . ' = ? WHERE "id" = ?',
                array(self::vectorLiteral($write->embedding()), $write->hash(), $embeddedAt, $write->id())
            );
            $db->commit();
        } catch (\Throwable $e) {
            // The original failure is the one to report, even if the rollback fails too.
            try {
                if ($db->isUnderTransaction()) {
                    $db->rollback();
                }
            } catch (\Throwable $rollbackFailure) {
            }
            throw $e;
        }

        return $embeddedAt;
    }

    /**
     * This function returns the pgvector text form of an embedding: [n,n,...].
     *
     * @param float[] $embedding
     * @return string
     */
    private static function vectorLiteral(array $embedding)
    {
        return '[' . implode(',', array_map(static function ($number) {
            return var_export((float) $number, true);
        }, $embedding)) . ']';
    }

    /**
     * @param string $identifier
     * @return string
     * @throws \Gaia\Exception\Exception When the name is not a plain identifier
     */
    private function quote($identifier)
    {
        if (!is_string($identifier) || !preg_match(self::IDENTIFIER, $identifier)) {
            throw new \Gaia\Exception\Exception('Search embeddings got an invalid table or column name.');
        }

        return '"' . $identifier . '"';
    }

    /**
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    private function db()
    {
        if ($this->db === null) {
            $this->db = \Phalcon\Di::getDefault()->getShared('db');
        }

        return $this->db;
    }
}
