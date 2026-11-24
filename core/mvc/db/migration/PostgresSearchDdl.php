<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Db\Migration;

/**
 * Builds the PostgreSQL DDL for the column and index kinds Phalcon's Column and
 * Index cannot express: pgvector columns, generated tsvector columns and indexes
 * that need an access method or operator class (GIN, trigram GIN, HNSW).
 *
 * Model metadata uses them like this:
 *
 *     'embedding' => ['name' => 'embedding', 'type' => 'vector', 'dimensions' => 1536, 'null' => true],
 *     'searchVector' => [
 *         'name' => 'searchVector', 'type' => 'tsvector', 'null' => true,
 *         'generated' => ['config' => 'english', 'sources' => ['subject' => 'A', 'description' => 'B']],
 *     ],
 *     'indexes' => ['searchVector' => 'gin', 'subject' => 'gin_trgm', 'embedding' => 'hnsw_cosine'],
 *
 * The class only builds SQL; running it is the migration handler's job.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation\Mvc\Db\Migration
 * @category PostgresSearchDdl
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PostgresSearchDdl
{
    /**
     * Index kinds this class builds, mapped to their USING clause and operator class.
     *
     * @var array
     */
    private const INDEX_KINDS = array(
        'gin' => array('method' => 'gin', 'opclass' => ''),
        'gin_trgm' => array('method' => 'gin', 'opclass' => 'gin_trgm_ops'),
        'hnsw_cosine' => array('method' => 'hnsw', 'opclass' => 'vector_cosine_ops'),
    );

    /**
     * Text search weights setweight() accepts.
     *
     * @var array
     */
    private const WEIGHTS = array('A', 'B', 'C', 'D');

    /**
     * Most dimensions a pgvector vector column takes.
     *
     * @var int
     */
    public const VECTOR_MAX_DIMENSIONS = 16000;

    /**
     * Most dimensions an hnsw index on a vector column takes.
     *
     * @var int
     */
    public const HNSW_MAX_DIMENSIONS = 2000;

    /**
     * Field types a tsvector may read. Their ::text cast is immutable, which a stored
     * generated column needs (dates and numbers cast through the session settings).
     *
     * @var array
     */
    public const TEXT_SOURCE_TYPES = array('varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext');

    /**
     * The text search config of a tsvector that names none; queries against it must use the same one.
     *
     * @var string
     */
    public const DEFAULT_TEXT_SEARCH_CONFIG = 'english';

    /**
     * This function tells whether a metadata field type is built by this class.
     *
     * @param string $type Field type from model metadata
     * @return bool
     */
    public static function isSearchType($type)
    {
        return in_array($type, array('vector', 'tsvector'), true);
    }

    /**
     * This function tells whether a metadata index type is built by this class.
     *
     * @param mixed $kind Index type from model metadata
     * @return bool
     */
    public static function isSearchIndexKind($kind)
    {
        return is_string($kind) && isset(self::INDEX_KINDS[$kind]);
    }

    /**
     * This function returns the column type and constraints for a vector or tsvector
     * field, e.g. `vector(1536)` or `tsvector GENERATED ALWAYS AS (...) STORED`.
     *
     * @param array $schema Field schema from model metadata
     * @param array $fields All field schemas of the model, keyed by field name
     * @return string
     * @throws \Gaia\Exception\MigrationDriver When the field definition is incomplete
     */
    public function columnDefinition(array $schema, array $fields)
    {
        if ($schema['type'] === 'vector') {
            return $this->vectorDefinition($schema);
        }
        if ($schema['type'] === 'tsvector') {
            return $this->tsvectorDefinition($schema, $fields);
        }

        throw new \Gaia\Exception\MigrationDriver("Field {$schema['name']} is not a search field.");
    }

    /**
     * This function returns the CREATE INDEX statement for a search index kind.
     *
     * @param string $tableName Table the index belongs to
     * @param string $columnName Indexed column
     * @param string $kind Index kind (gin, gin_trgm or hnsw_cosine)
     * @return string
     */
    public function createIndexSql($tableName, $columnName, $kind)
    {
        $definition = self::INDEX_KINDS[$kind];
        $opclass = $definition['opclass'] === '' ? '' : ' ' . $definition['opclass'];

        return 'CREATE INDEX IF NOT EXISTS ' . self::quote($tableName . '_' . $columnName)
            . ' ON ' . self::quote($tableName)
            . ' USING ' . $definition['method']
            . ' (' . self::quote($columnName) . $opclass . ')';
    }

    /**
     * This function returns the CREATE TABLE statement of a search vocabulary table (D-010): one row per project and
     * word (next '') or adjacent word pair (term, next), with the number of records it occurs in.
     *
     * @param string $tableName Vocabulary table
     * @return string
     */
    public function createVocabularyTableSql($tableName)
    {
        return 'CREATE TABLE IF NOT EXISTS ' . self::quote($tableName) . ' ('
            . '"projectId" varchar(36) NOT NULL, '
            . 'term text NOT NULL, '
            . "next text NOT NULL DEFAULT '', "
            . 'docs integer NOT NULL, '
            . 'PRIMARY KEY (term, next, "projectId"))';
    }

    /**
     * This function returns the extension a column definition or index kind needs, or
     * null when it needs none.
     *
     * @param string $definitionOrKind Column definition from columnDefinition() or an index kind
     * @return string|null
     */
    public static function requiredExtension($definitionOrKind)
    {
        if ($definitionOrKind === 'hnsw_cosine' || strpos($definitionOrKind, 'vector(') === 0) {
            return 'vector';
        }
        if ($definitionOrKind === 'gin_trgm') {
            return 'pg_trgm';
        }

        return null;
    }

    /**
     * This function tells whether a column definition is a vector, whose values cannot
     * be rebuilt by the database (a generated tsvector can).
     *
     * @param string $definition Column definition from columnDefinition()
     * @return bool
     */
    public static function isVectorDefinition($definition)
    {
        return strpos($definition, 'vector(') === 0;
    }

    /**
     * This function returns the dimensions of a vector column definition or type
     * (vector(N)), or null for anything else.
     *
     * @param string $definition
     * @return int|null
     */
    public static function vectorDimensions($definition)
    {
        return preg_match('/^vector\((\d+)\)$/', $definition, $match) ? (int) $match[1] : null;
    }

    /**
     * This function tells whether an existing index, as the catalogs describe it, matches
     * an index kind: the same access method and operator class (the type's default one
     * for a kind without an explicit class).
     *
     * @param string $method Access method (pg_am.amname)
     * @param string $opclass Operator class of the indexed column (pg_opclass.opcname)
     * @param bool $defaultOpclass Whether that class is the type's default (pg_opclass.opcdefault)
     * @param string $kind Index kind (gin, gin_trgm or hnsw_cosine)
     * @return bool
     */
    public function indexMatchesKind($method, $opclass, $defaultOpclass, $kind)
    {
        $definition = self::INDEX_KINDS[$kind];
        if ($method !== $definition['method']) {
            return false;
        }
        if ($definition['opclass'] === '') {
            return $defaultOpclass;
        }

        return $opclass === $definition['opclass'];
    }

    /**
     * This function returns a short signature of a column definition. The migration
     * stores it as the column comment, so a later run can tell whether the definition
     * changed without parsing PostgreSQL's normalized generation expression.
     *
     * @param string $definition Column definition from columnDefinition()
     * @return string
     */
    public function signature($definition)
    {
        return 'gaia:' . sha1($definition);
    }

    /**
     * This function builds a pgvector column type.
     *
     * @param array $schema Field schema from model metadata
     * @return string
     * @throws \Gaia\Exception\MigrationDriver
     */
    private function vectorDefinition(array $schema)
    {
        $raw = isset($schema['dimensions']) ? $schema['dimensions'] : null;
        $isInteger = is_int($raw) || (is_string($raw) && ctype_digit($raw));
        $dimensions = $isInteger ? (int) $raw : 0;
        if ($dimensions < 1 || $dimensions > self::VECTOR_MAX_DIMENSIONS) {
            throw new \Gaia\Exception\MigrationDriver(
                "Vector field {$schema['name']} needs 'dimensions' between 1 and " . self::VECTOR_MAX_DIMENSIONS . '.'
            );
        }

        return "vector({$dimensions})";
    }

    /**
     * This function builds a stored generated tsvector column from weighted source fields.
     *
     * @param array $schema Field schema from model metadata
     * @param array $fields All field schemas of the model, keyed by field name
     * @return string
     * @throws \Gaia\Exception\MigrationDriver
     */
    private function tsvectorDefinition(array $schema, array $fields)
    {
        $generated = isset($schema['generated']) ? $schema['generated'] : array();
        if (empty($generated['sources']) || !is_array($generated['sources'])) {
            throw new \Gaia\Exception\MigrationDriver(
                "Tsvector field {$schema['name']} needs 'generated' => ['sources' => [field => weight]]."
            );
        }

        $config = isset($generated['config']) ? $generated['config'] : self::DEFAULT_TEXT_SEARCH_CONFIG;
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $config)) {
            throw new \Gaia\Exception\MigrationDriver(
                "Tsvector field {$schema['name']} has an invalid text search config '{$config}'."
            );
        }

        $parts = array();
        foreach ($generated['sources'] as $field => $weight) {
            if (!isset($fields[$field])) {
                throw new \Gaia\Exception\MigrationDriver(
                    "Tsvector field {$schema['name']} reads unknown field '{$field}'."
                );
            }
            $sourceType = isset($fields[$field]['type']) ? $fields[$field]['type'] : '';
            if (!in_array($sourceType, self::TEXT_SOURCE_TYPES, true)) {
                throw new \Gaia\Exception\MigrationDriver(
                    "Tsvector field {$schema['name']} reads '{$field}' of type '{$sourceType}'; only text fields can feed it."
                );
            }
            if (!in_array($weight, self::WEIGHTS, true)) {
                throw new \Gaia\Exception\MigrationDriver(
                    "Tsvector field {$schema['name']} gives '{$field}' weight '{$weight}'; use A, B, C or D."
                );
            }
            $column = self::quote($fields[$field]['name']);
            $parts[] = "setweight(to_tsvector('{$config}'::regconfig, coalesce({$column}::text, '')), '{$weight}')";
        }

        return 'tsvector GENERATED ALWAYS AS (' . implode(' || ', $parts) . ') STORED';
    }

    /**
     * This function quotes a PostgreSQL identifier.
     *
     * @param string $identifier
     * @return string
     */
    private static function quote($identifier)
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
