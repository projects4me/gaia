<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Where a searchable type's vocabulary comes from and where it is kept (D-010): the words and adjacent word pairs of
 * the fts fields of the model's table, counted per project in the table search_vocabulary_<type>. The project is the
 * model's Project group key (acl.groupKeys.Project); a type without one is counted under the project ''.
 *
 * The migration creates the vocabulary table and empties it when the signature changes (another fts field list or
 * group column); SearchVocabularyBuilder fills it and SearchVocabulary reads it.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class VocabularySource
{
    /**
     * Prefix of the vocabulary tables, followed by the search type.
     *
     * @var string
     */
    public const TABLE_PREFIX = 'search_vocabulary_';

    /**
     * A search type, as SearchBlock validates it; it keeps the table name a plain identifier.
     *
     * @var string
     */
    private const TYPE_PATTERN = '/^[a-z][a-z0-9_]{0,49}\z/';

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $recordTable;

    /**
     * @var string[]
     */
    private $columns;

    /**
     * @var string|null
     */
    private $groupColumn;

    /**
     * @param string $type The search type
     * @param string $recordTable The model's table
     * @param string[] $columns The fts columns, in block order
     * @param string|null $groupColumn The project column, null when the type has none
     */
    public function __construct($type, $recordTable, array $columns, $groupColumn)
    {
        $this->type = $type;
        $this->recordTable = $recordTable;
        $this->columns = array_values($columns);
        $this->groupColumn = $groupColumn;
    }

    /**
     * This function returns the vocabulary source of a model, or null when the model has no search block.
     *
     * @param array $metadata The model's metadata, as SearchBlock::apply() returns it
     * @return self|null
     */
    public static function fromMetadata(array $metadata)
    {
        if (!isset($metadata[SearchBlock::KEY])) {
            return null;
        }

        $block = $metadata[SearchBlock::KEY];
        $fields = $metadata['fields'];
        $columns = array();
        foreach (array_keys($block['fts']) as $field) {
            $columns[] = $fields[$field]['name'];
        }
        $groupField = isset($metadata['acl']['groupKeys']['Project']) ? $metadata['acl']['groupKeys']['Project'] : null;
        $groupColumn = $groupField !== null && isset($fields[$groupField]) ? $fields[$groupField]['name'] : null;

        return new self($block['type'], $metadata['tableName'], $columns, $groupColumn);
    }

    /**
     * This function returns the vocabulary table of a search type.
     *
     * @param string $type
     * @return string
     * @throws \Gaia\Exception\Exception When the type is not a search type
     */
    public static function tableFor($type)
    {
        if (!is_string($type) || !preg_match(self::TYPE_PATTERN, $type)) {
            throw new \Gaia\Exception\Exception("'" . (is_string($type) ? $type : gettype($type)) . "' is not a search type.");
        }

        return self::TABLE_PREFIX . $type;
    }

    /**
     * @return string
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function table()
    {
        return self::tableFor($this->type);
    }

    /**
     * @return string
     */
    public function recordTable()
    {
        return $this->recordTable;
    }

    /**
     * @return string[]
     */
    public function columns()
    {
        return $this->columns;
    }

    /**
     * @return string|null
     */
    public function groupColumn()
    {
        return $this->groupColumn;
    }

    /**
     * This function returns the signature of what the counts were made from: the record table, the fts columns and
     * the project column. The migration keeps it as the vocabulary table's comment.
     *
     * @return string
     */
    public function signature()
    {
        return 'gaia:' . sha1(json_encode(array($this->recordTable, $this->columns, $this->groupColumn)));
    }
}
