<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Db\Migration\PostgresSearchDdl;
use Gaia\Libraries\Meta\Manager;

/**
 * A model's search block: the `search` key of its metadata, which makes the model searchable.
 *
 *     'search' => [
 *         'type' => 'issue',                               // name in /search?types= and in search events
 *         'fts' => ['subject' => 'A', 'description' => 'B'], // field => weight A-D, feeds searchVector
 *         'fuzzy' => ['subject'],                            // a gin_trgm index per field
 *         'semantic' => ['subject', 'description'],          // ordered fields of the canonical semantic text
 *         'exact' => ['issueNumber'],                        // fields matched as they are
 *         'display' => ['title' => 'subject', 'snippet' => 'description'],
 *     ],
 *
 * The search data lives on the model's own table, so apply() validates the block and adds the search columns and
 * their indexes to the metadata, every column flagged `'system' => true` (D-071): the API never reads, writes or
 * audits them. searchVector is a stored generated tsvector built from fts. With semantic fields the model also gets
 * the canonical semantic text and its hash (SemanticText), the embedding and the hash of the text it was made from,
 * and searchIndexBehavior, which keeps the text and hash current on every save. It is also opened to the Ares service
 * (acl.service, D-072): Ares reads the stored text and hashes, and writes the embedding for the current hash.
 *
 * Type uniqueness across models is checked by SearchableRegistry, which sees every model.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchBlock
{
    /**
     * The metadata key that holds the search block.
     *
     * @var string
     */
    public const KEY = 'search';

    /**
     * Dimensions of the semantic embedding column.
     *
     * @var int
     */
    public const EMBEDDING_DIMENSIONS = 768;

    /**
     * The injected column names.
     *
     * @var string
     */
    public const VECTOR = 'searchVector';
    public const SEMANTIC_TEXT = 'searchSemanticText';
    public const SEMANTIC_HASH = 'searchSemanticHash';
    public const EMBEDDING = 'searchEmbedding';
    public const EMBEDDING_HASH = 'searchEmbeddingHash';
    public const EMBEDDED_AT = 'searchEmbeddedAt';

    /**
     * The behavior that keeps the semantic text current, attached to every model with semantic fields.
     *
     * @var string
     */
    public const BEHAVIOR = 'searchIndexBehavior';

    /**
     * The service kind that reads the semantic text and writes the embedding (config/serviceclients.php).
     *
     * @var string
     */
    public const SERVICE_KIND = 'ares';

    /**
     * The system fields the service reads, and the ones it writes.
     *
     * @var array
     */
    public const SERVICE_READS = array(self::SEMANTIC_TEXT, self::SEMANTIC_HASH, self::EMBEDDING_HASH, self::EMBEDDED_AT);
    public const SERVICE_WRITES = array(self::EMBEDDING, self::EMBEDDING_HASH);

    /**
     * Keys a search block may have.
     *
     * @var array
     */
    private const KEYS = array('type', 'fts', 'fuzzy', 'semantic', 'exact', 'display');

    /**
     * Keys of the display entry.
     *
     * @var array
     */
    private const DISPLAY_KEYS = array('title', 'snippet');

    /**
     * Text search weights.
     *
     * @var array
     */
    private const WEIGHTS = array('A', 'B', 'C', 'D');

    /**
     * Field types a gin_trgm index takes as they are: binary-coercible to text, which char is not.
     *
     * @var array
     */
    private const FUZZY_TYPES = array('varchar', 'text', 'tinytext', 'mediumtext', 'longtext');

    /**
     * A search type: lower case, as it appears in /search?types= and in search events.
     *
     * @var string
     */
    private const TYPE_PATTERN = '/^[a-z][a-z0-9_]{0,49}\z/';

    /**
     * This function validates a model's search block and returns the metadata with the search columns and indexes
     * added. Metadata without a search block is returned unchanged.
     *
     * @param string $model Model name, for the error messages
     * @param array $metadata The model's metadata, as in app/metadata/model/{Model}.php
     * @return array
     * @throws \Gaia\Exception\Exception When the block is invalid
     */
    public static function apply($model, array $metadata)
    {
        if (!array_key_exists(self::KEY, $metadata)) {
            return $metadata;
        }

        $block = self::normalize($model, $metadata);
        if (isset($metadata['isView'])) {
            self::fail($model, 'is a view, which cannot hold the search columns and indexes');
        }
        $metadata[self::KEY] = $block;
        $fields = isset($metadata['fields']) ? $metadata['fields'] : array();
        $indexes = isset($metadata['indexes']) ? $metadata['indexes'] : array();

        foreach (self::columns($block) as $name => $schema) {
            if (isset($fields[$name])) {
                self::fail($model, "declares field '{$name}', which its search block adds");
            }
            $fields[$name] = $schema;
        }

        $searchIndexes = array(self::VECTOR => 'gin');
        if ($block['semantic'] !== array()) {
            $searchIndexes[self::EMBEDDING] = 'hnsw_cosine';
        }
        foreach ($searchIndexes as $name => $kind) {
            if (isset($indexes[$name])) {
                self::fail($model, "declares index '{$name}', which its search block adds");
            }
            $indexes[$name] = $kind;
        }
        foreach ($block['fuzzy'] as $field) {
            if (isset($indexes[$field]) && $indexes[$field] !== 'gin_trgm') {
                self::fail($model, "fuzzy field '{$field}' already has a '"
                    . (is_array($indexes[$field]) ? 'multi-column' : $indexes[$field])
                    . "' index; a field takes one index and fuzzy needs gin_trgm");
            }
            $indexes[$field] = 'gin_trgm';
        }

        $metadata['fields'] = $fields;
        $metadata['indexes'] = $indexes;
        if ($block['semantic'] !== array()) {
            $behaviors = isset($metadata['behaviors']) ? $metadata['behaviors'] : array();
            if (!in_array(self::BEHAVIOR, $behaviors, true)) {
                $behaviors[] = self::BEHAVIOR;
            }
            $metadata['behaviors'] = $behaviors;
            $metadata = self::openToService($model, $metadata);
        }

        return $metadata;
    }

    /**
     * This function returns whether model metadata (as SearchBlock::apply() returns it) has semantic fields.
     *
     * @param array $metadata
     * @return bool
     */
    public static function isSemantic(array $metadata)
    {
        return !empty($metadata[self::KEY]['semantic']);
    }

    /**
     * This function opens a semantic model to the Ares service (D-072): get, with the stored text and hashes readable
     * through fields=, and update of the embedding alone, on every record that is not deleted, whatever its
     * authorization groups. The service's write is performed by SearchEmbeddings, never by a model save.
     *
     * @param string $model
     * @param array $metadata
     * @return array
     * @throws \Gaia\Exception\Exception When the model declares its own service access
     */
    private static function openToService($model, array $metadata)
    {
        $acl = isset($metadata['acl']) && is_array($metadata['acl']) ? $metadata['acl'] : array();
        if (isset($acl['service'])) {
            self::fail($model, "has semantic fields, which open acl.service to the " . self::SERVICE_KIND
                . " service; the model cannot declare its own");
        }

        // deleted is smallint on some tables and boolean on others, hence the cast.
        $acl['service'] = array(
            'actions' => array('get', 'update'),
            'kinds' => array(self::SERVICE_KIND),
            'filter' => "CAST({$model}.deleted AS INTEGER) = 0",
            'fields' => self::SERVICE_WRITES,
            'systemFields' => self::SERVICE_READS,
            // Ares indexes the issues of every project; it is no project member.
            'ignoreGroups' => true,
        );
        $metadata['acl'] = $acl;

        return $metadata;
    }

    /**
     * This function returns the field schemas a valid search block adds, keyed by field name.
     *
     * @param array $block A normalized search block
     * @return array
     */
    private static function columns(array $block)
    {
        $columns = array(
            self::VECTOR => array(
                'type' => 'tsvector',
                'generated' => array('sources' => $block['fts']),
            ),
        );
        if ($block['semantic'] !== array()) {
            $columns[self::SEMANTIC_TEXT] = array('type' => 'text');
            $columns[self::SEMANTIC_HASH] = array('type' => 'varchar', 'length' => '64');
            // The embedding is written by the indexer alone: a record save must not write back a stale copy.
            $columns[self::EMBEDDING] = array(
                'type' => 'vector',
                'dimensions' => self::EMBEDDING_DIMENSIONS,
                'ormWrite' => false,
            );
            $columns[self::EMBEDDING_HASH] = array('type' => 'varchar', 'length' => '64', 'ormWrite' => false);
            $columns[self::EMBEDDED_AT] = array('type' => 'datetime', 'ormWrite' => false);
        }

        foreach ($columns as $name => $schema) {
            $columns[$name] = array('name' => $name) + $schema + array('null' => true, 'system' => true);
        }

        return $columns;
    }

    /**
     * This function validates a search block and returns it with every key present.
     *
     * @param string $model
     * @param array $metadata
     * @return array
     * @throws \Gaia\Exception\Exception
     */
    private static function normalize($model, array $metadata)
    {
        $block = $metadata[self::KEY];
        if (!is_array($block)) {
            self::fail($model, 'needs an array');
        }
        $unknown = array_diff(array_keys($block), self::KEYS);
        if ($unknown !== array()) {
            self::fail($model, "has unknown key '" . reset($unknown) . "'; use " . implode(', ', self::KEYS));
        }

        $fields = isset($metadata['fields']) && is_array($metadata['fields']) ? $metadata['fields'] : array();

        $type = isset($block['type']) ? $block['type'] : null;
        if (!is_string($type) || !preg_match(self::TYPE_PATTERN, $type)) {
            self::fail($model, "needs a 'type': lower case letters, digits and _, starting with a letter");
        }

        $fts = isset($block['fts']) ? $block['fts'] : null;
        if (!is_array($fts) || $fts === array() || self::isList($fts)) {
            self::fail($model, "needs 'fts' => [field => weight]");
        }
        foreach ($fts as $field => $weight) {
            self::assertField($model, 'fts', $field, $fields, PostgresSearchDdl::TEXT_SOURCE_TYPES);
            if (!in_array($weight, self::WEIGHTS, true)) {
                self::fail($model, "gives fts field '{$field}' weight '" . self::describe($weight) . "'; use A, B, C or D");
            }
        }

        $normalized = array('type' => $type, 'fts' => $fts);
        foreach (array('fuzzy' => self::FUZZY_TYPES, 'semantic' => null, 'exact' => null) as $key => $types) {
            $list = isset($block[$key]) ? $block[$key] : array();
            if (!is_array($list) || !self::isList($list)) {
                self::fail($model, "needs '{$key}' as a list of fields");
            }
            foreach ($list as $field) {
                self::assertField($model, $key, $field, $fields, $types);
            }
            if (count(array_unique($list)) !== count($list)) {
                self::fail($model, "lists a field twice in '{$key}'");
            }
            $normalized[$key] = $list;
        }

        $display = isset($block['display']) ? $block['display'] : null;
        if (!is_array($display) || !isset($display['title'])) {
            self::fail($model, "needs 'display' => ['title' => field, 'snippet' => field]");
        }
        $unknown = array_diff(array_keys($display), self::DISPLAY_KEYS);
        if ($unknown !== array()) {
            self::fail($model, "has unknown display key '" . reset($unknown) . "'; use title, snippet");
        }
        foreach ($display as $key => $field) {
            // The optional snippet may be given as null
            if ($key === 'snippet' && $field === null) {
                continue;
            }
            // The snippet is a ts_headline over the text, so it needs a text field like fts
            $types = $key === 'snippet' ? PostgresSearchDdl::TEXT_SOURCE_TYPES : null;
            self::assertField($model, "display {$key}", $field, $fields, $types);
        }
        $normalized['display'] = array(
            'title' => $display['title'],
            'snippet' => isset($display['snippet']) ? $display['snippet'] : null,
        );

        return $normalized;
    }

    /**
     * This function checks that a search block entry names a declared, non-system field (of a text type when the
     * entry needs one).
     *
     * @param string $model
     * @param string $entry The block entry, for the message
     * @param mixed $field
     * @param array $fields The model's declared fields
     * @param array|null $types The field types the entry takes, or null for any
     * @return void
     * @throws \Gaia\Exception\Exception
     */
    private static function assertField($model, $entry, $field, array $fields, $types)
    {
        if (!is_string($field) || !isset($fields[$field])) {
            self::fail($model, "names unknown field '" . self::describe($field) . "' in {$entry}");
        }
        if (Manager::isSystemFieldMeta($fields[$field])) {
            self::fail($model, "names system field '{$field}' in {$entry}");
        }
        $type = isset($fields[$field]['type']) ? $fields[$field]['type'] : '';
        if ($types !== null && !in_array($type, $types, true)) {
            self::fail($model, "names '{$field}' of type '{$type}' in {$entry}; use a field of type "
                . implode(', ', $types));
        }
    }

    /**
     * Whether an array is a list: keys 0, 1, 2, ... in order.
     *
     * @param array $value
     * @return bool
     */
    private static function isList(array $value)
    {
        return array_keys($value) === range(0, count($value) - 1) || $value === array();
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function describe($value)
    {
        return is_scalar($value) ? (string) $value : gettype($value);
    }

    /**
     * @param string $model
     * @param string $problem
     * @return void
     * @throws \Gaia\Exception\Exception
     */
    private static function fail($model, $problem)
    {
        throw new \Gaia\Exception\Exception("Search block of model {$model} {$problem}.");
    }
}
