<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * The rows the search reindex reads and the semantic text it stores, on a searchable model's own table. The text
 * update is a bound UPDATE of the two search columns alone: no behaviours, no audit and no dateModified, since the
 * record itself did not change.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SemanticRecords
{
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
     * This function returns the next page of non-deleted records, ordered by id: the id, the semantic fields and the
     * two search hashes of each. deleted is smallint on some tables and boolean on others, hence the cast.
     *
     * @param string $table
     * @param array $fields The semantic fields
     * @param string $afterId The last id of the previous page, '' for the first page
     * @param int $limit
     * @return array[]
     */
    public function page($table, array $fields, $afterId, $limit)
    {
        $columns = array_merge(array('id'), $fields, array(SearchBlock::DESIRED_HASH, SearchBlock::ACTUAL_HASH));

        return $this->db()->fetchAll(
            'SELECT ' . implode(', ', array_map(array($this, 'quote'), array_unique($columns)))
            . ' FROM ' . $this->quote($table) . ' WHERE "deleted"::int = 0 AND "id" > ? ORDER BY "id" LIMIT ?',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            array((string) $afterId, (int) $limit),
            array(\Phalcon\Db\Column::BIND_PARAM_STR, \Phalcon\Db\Column::BIND_PARAM_INT)
        );
    }

    /**
     * This function stores a record's semantic text and hash, unless the stored hash is no longer the one the reindex
     * read: a save in between already stored a newer text and announced it.
     *
     * @param string $table
     * @param string $id
     * @param string $text
     * @param string $hash
     * @param string|null $readHash The hash the page returned
     * @return bool Whether the record was updated
     */
    public function store($table, $id, $text, $hash, $readHash)
    {
        $db = $this->db();
        $db->execute(
            'UPDATE ' . $this->quote($table) . ' SET ' . $this->quote(SearchBlock::SEMANTIC_TEXT) . ' = ?, '
            . $this->quote(SearchBlock::DESIRED_HASH) . ' = ? WHERE "id" = ? AND '
            . $this->quote(SearchBlock::DESIRED_HASH) . ' IS NOT DISTINCT FROM ?',
            array((string) $text, (string) $hash, (string) $id, $readHash === null ? null : (string) $readHash)
        );

        return $db->affectedRows() === 1;
    }

    /**
     * @param string $identifier
     * @return string
     * @throws \Gaia\Exception\Exception When the name is not a plain identifier
     */
    private function quote($identifier)
    {
        if (!is_string($identifier) || !preg_match(self::IDENTIFIER, $identifier)) {
            throw new \Gaia\Exception\Exception('Search reindex got an invalid table or column name.');
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
