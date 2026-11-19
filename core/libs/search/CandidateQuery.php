<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Queries a type's table (alias t) always inside the target's scope: the searchers' candidate queries, so a record
 * the user may not see never takes a place in the ranking or the LIMIT (D-022), and the load of the results' display
 * values. Callers give conditions and orders over identifiers from metadata; every value is a bound parameter.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CandidateQuery
{
    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface
     */
    private $db;

    /**
     * @param \Phalcon\Db\Adapter\AdapterInterface $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * This function returns a column of the type's table, quoted, for a condition or an order.
     *
     * @param string $column
     * @return string
     */
    public function column($column)
    {
        return 't.' . $this->db->escapeIdentifier($column);
    }

    /**
     * This function runs a callback with a database setting changed, e.g. a pg_trgm threshold, and puts the
     * setting back afterwards so it does not leak into later queries on the same connection.
     *
     * @param string $name
     * @param string $value
     * @param callable $callback
     * @return mixed What the callback returns
     */
    public function withSetting($name, $value, callable $callback)
    {
        $previous = $this->setting($name, (string) $value);
        try {
            return $callback();
        } finally {
            $this->setting($name, $previous);
        }
    }

    /**
     * @param string $name
     * @param string|null $value Null resets the setting to its default
     * @return string|null The value before the change, null when the setting was not defined
     */
    private function setting($name, $value)
    {
        // missing_ok: a setting of an extension library the backend has not loaded yet (pg_trgm on a new
        // connection) reads as NULL instead of failing; set_config then keeps it as a placeholder the library
        // adopts when it loads, and restoring NULL resets it to the default
        $row = $this->db->fetchOne(
            'SELECT current_setting(:search_current, true) AS "previous",'
                . ' set_config(:search_setting, :search_value, false)',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            array('search_current' => $name, 'search_setting' => $name, 'search_value' => $value)
        );

        return $row['previous'];
    }

    /**
     * This function returns the ids of the matching records in scope, in the given order.
     *
     * @param SearchTarget $target
     * @param string $condition The match condition, over columns from column() and :search_ parameters
     * @param string|null $order The ranking expression, best first, or null for none
     * @param array $bind The condition's and order's parameters, named search_*
     * @param int $limit
     * @return array
     */
    public function ids(SearchTarget $target, $condition, $order, array $bind, $limit)
    {
        $rows = $this->rows(
            $target,
            array(),
            $condition,
            $bind,
            ' ORDER BY ' . ($order === null ? '' : $order . ', ') . $this->column('id') . ' LIMIT ' . (int) $limit
        );

        return array_map(function ($row) {
            return $row['id'];
        }, $rows);
    }

    /**
     * This function returns the id and the given columns of the records in scope that have one of the given ids.
     *
     * @param SearchTarget $target
     * @param array $ids
     * @param array $select Expressions keyed by their alias
     * @param array $bind The expressions' parameters, named search_*
     * @return array Rows keyed by id
     */
    public function rowsById(SearchTarget $target, array $ids, array $select, array $bind)
    {
        $placeholders = array();
        foreach (array_values($ids) as $i => $id) {
            $placeholders[] = ':search_id' . $i;
            $bind['search_id' . $i] = $id;
        }
        if ($placeholders === array()) {
            return array();
        }

        $rows = array();
        $condition = $this->column('id') . ' IN (' . implode(', ', $placeholders) . ')';
        foreach ($this->rows($target, $select, $condition, $bind, '') as $row) {
            $rows[$row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @param SearchTarget $target
     * @param array $select Expressions keyed by their alias
     * @param string $condition
     * @param array $bind
     * @param string $tail ORDER BY and LIMIT, or empty
     * @return array
     */
    private function rows(SearchTarget $target, array $select, $condition, array $bind, $tail)
    {
        $id = $this->column('id');
        $columns = array($id . ' AS "id"');
        foreach ($select as $alias => $expression) {
            $columns[] = $expression . ' AS ' . $this->db->escapeIdentifier($alias);
        }
        $sql = 'SELECT ' . implode(', ', $columns)
            . ' FROM ' . $this->db->escapeIdentifier($target->table()) . ' AS t'
            . ' WHERE ' . $id . ' IN (' . $target->scopeSql() . ') AND (' . $condition . ')' . $tail;

        $scopeBind = $target->scopeBind();
        if (array_intersect_key($scopeBind, $bind) !== array()) {
            throw new \Gaia\Exception\Exception('A search parameter has the name of a scope parameter.');
        }
        $rows = $this->db->fetchAll($sql, \Phalcon\Db\Enum::FETCH_ASSOC, $scopeBind + $bind);
        foreach ($rows as $i => $row) {
            $rows[$i]['id'] = (string) $row['id'];
        }

        return $rows;
    }
}
