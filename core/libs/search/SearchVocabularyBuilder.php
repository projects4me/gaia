<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Libraries\Meta\Manager;

/**
 * Rebuilds the vocabulary of the searchable types (D-010): for every project, the number of non-deleted records whose
 * fts fields hold a word, and an adjacent word pair. The text is read in lower case with HTML tags and entities
 * removed and every run of characters that are not letters or digits read as one space; a pair never spans two
 * fields. A word longer than MAX_WORD_LENGTH (a pasted hash or blob) is left out, with the pairs it is in.
 *
 * The rebuild replaces the counts of a type in one transaction, so readers see the old counts until it commits.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchVocabularyBuilder
{
    /**
     * Longest word kept, in characters.
     *
     * @var int
     */
    public const MAX_WORD_LENGTH = 64;

    /**
     * Table and column names the queries take; they come from model metadata, never from the request.
     *
     * @var string
     */
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*\z/';

    /**
     * @var SearchableRegistry
     */
    private $registry;

    /**
     * @var Manager
     */
    private $metaManager;

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface|null
     */
    private $db;

    /**
     * @param SearchableRegistry $registry
     * @param Manager $metaManager
     * @param \Phalcon\Db\Adapter\AdapterInterface|null $db Null uses the shared connection
     */
    public function __construct(SearchableRegistry $registry, Manager $metaManager, $db = null)
    {
        $this->registry = $registry;
        $this->metaManager = $metaManager;
        $this->db = $db;
    }

    /**
     * This function rebuilds the vocabulary of one search type, or of every type.
     *
     * @param string|null $type
     * @return array Counts per type: words and pairs (distinct over all projects) and ms (time taken)
     * @throws \Gaia\Exception\Exception When the type is unknown
     */
    public function run($type = null)
    {
        $types = $type === null ? $this->registry->types() : array($type);
        $counts = array();
        foreach ($types as $searchType) {
            $block = $this->registry->forType($searchType);
            if ($block === null) {
                throw new \Gaia\Exception\Exception("Search type '{$searchType}' is unknown.");
            }
            $source = VocabularySource::fromMetadata($this->metaManager->getModelMeta($block['model']));
            if ($source === null) {
                throw new \Gaia\Exception\Exception("Search type '{$searchType}' has no search block.");
            }
            $started = microtime(true);
            $counts[$searchType] = $this->rebuild($source)
                + array('ms' => (int) round((microtime(true) - $started) * 1000));
        }

        return $counts;
    }

    /**
     * This function replaces the counts of one vocabulary with the ones its records give now.
     *
     * @param VocabularySource $source
     * @return array The words and pairs now in the vocabulary, distinct over all projects
     */
    public function rebuild(VocabularySource $source)
    {
        $table = $this->quote($source->table());
        $db = $this->db();
        $db->begin();
        try {
            // A second rebuild of the same type (another container starting, a manual run) waits for this one.
            $db->execute('SELECT pg_advisory_xact_lock(hashtext(?))', array($source->table()));
            $db->execute("DELETE FROM {$table}");
            $db->execute($this->insertSql($source), array(self::MAX_WORD_LENGTH, self::MAX_WORD_LENGTH));
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        $row = $db->fetchOne(
            "SELECT COUNT(DISTINCT term) FILTER (WHERE next = '') AS words,"
            . " COUNT(DISTINCT (term, next)) FILTER (WHERE next <> '') AS pairs FROM {$table}",
            \Phalcon\Db\Enum::FETCH_ASSOC
        );

        return array('words' => (int) $row['words'], 'pairs' => (int) $row['pairs']);
    }

    /**
     * This function builds the INSERT ... SELECT that counts the words and pairs of a type's records. Both its
     * parameters are the longest word kept.
     *
     * @param VocabularySource $source
     * @return string
     */
    private function insertSql(VocabularySource $source)
    {
        $texts = array();
        foreach ($source->columns() as $position => $column) {
            $texts[] = '(' . ($position + 1) . ', record.' . $this->quote($column) . '::text)';
        }
        $project = $source->groupColumn() === null
            ? "''::text"
            : 'coalesce(record.' . $this->quote($source->groupColumn()) . "::text, '')";

        // Tags and entities first, so their names are not read as words; then every run of other characters. A tag
        // starts with a letter, / or !, so the text between a lone < and a later > stays.
        $normalized = "regexp_replace(regexp_replace(regexp_replace(lower(field.body), '<[a-z/!][^>]*>', ' ', 'g'),"
            . " '&#?[a-z0-9]+;', ' ', 'g'), '[^[:alnum:]]+', ' ', 'g')";

        return 'INSERT INTO ' . $this->quote($source->table()) . ' ("projectId", term, next, docs)'
            . ' WITH texts AS ('
            . "SELECT record.\"id\" AS id, {$project} AS project, field.position, {$normalized} AS body"
            . ' FROM ' . $this->quote($source->recordTable()) . ' record'
            . ' CROSS JOIN LATERAL (VALUES ' . implode(', ', $texts) . ') AS field(position, body)'
            . ' WHERE record."deleted"::int = 0 AND field.body IS NOT NULL'
            . '), words AS ('
            . 'SELECT texts.id, texts.project, token.word,'
            . ' lead(token.word) OVER (PARTITION BY texts.id, texts.position ORDER BY token.ordinal) AS next'
            . " FROM texts CROSS JOIN LATERAL unnest(string_to_array(btrim(texts.body), ' '))"
            . ' WITH ORDINALITY AS token(word, ordinal)'
            . "), kept AS (SELECT id, project, word, next FROM words WHERE word <> '' AND length(word) <= ?)"
            . ', terms AS ('
            . "SELECT id, project, word AS term, '' AS next FROM kept"
            . ' UNION SELECT kept.id, kept.project, kept.word, kept.next FROM kept'
            . " WHERE kept.next IS NOT NULL AND kept.next <> '' AND length(kept.next) <= ?"
            . ') SELECT project, term, next, COUNT(*) FROM terms GROUP BY project, term, next';
    }

    /**
     * @param string $identifier
     * @return string
     * @throws \Gaia\Exception\Exception
     */
    private function quote($identifier)
    {
        if (!is_string($identifier) || !preg_match(self::IDENTIFIER, $identifier)) {
            throw new \Gaia\Exception\Exception('Search vocabulary got an invalid table or column name.');
        }

        return '"' . $identifier . '"';
    }

    /**
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    private function db()
    {
        if ($this->db === null) {
            $this->db = \Phalcon\Di\Di::getDefault()->getShared('db');
        }

        return $this->db;
    }
}
