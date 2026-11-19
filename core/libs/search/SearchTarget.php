<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * One searchable type as a search runs over it: its search block, the model's table and fields, and the candidate
 * scope, a SQL subquery of the ids the user may see (deleted = 0 and the module's record scope) that every
 * candidate query must stay inside, before ranking and LIMIT (D-022).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchTarget
{
    /**
     * @var array
     */
    private $block;

    /**
     * @var string
     */
    private $table;

    /**
     * @var array
     */
    private $fields;

    /**
     * @var string
     */
    private $scopeSql;

    /**
     * @var array
     */
    private $scopeBind;

    /**
     * @param array $block The registry entry: the search block plus 'model'
     * @param string $table The model's table
     * @param array $fields The model's field schemas, keyed by field name
     * @param string $scopeSql SELECT of the ids in scope
     * @param array $scopeBind Named parameters of $scopeSql
     */
    public function __construct(array $block, $table, array $fields, $scopeSql, array $scopeBind)
    {
        $this->block = $block;
        $this->table = $table;
        $this->fields = $fields;
        $this->scopeSql = $scopeSql;
        $this->scopeBind = $scopeBind;
    }

    /**
     * @return string
     */
    public function type()
    {
        return $this->block['type'];
    }

    /**
     * @return string
     */
    public function model()
    {
        return $this->block['model'];
    }

    /**
     * This function returns an entry of the search block: fts, fuzzy, semantic, exact or display.
     *
     * @param string $key
     * @return array
     */
    public function block($key)
    {
        return $this->block[$key];
    }

    /**
     * @return string
     */
    public function table()
    {
        return $this->table;
    }

    /**
     * This function returns a field's schema, or an empty array.
     *
     * @param string $field
     * @return array
     */
    public function field($field)
    {
        return isset($this->fields[$field]) ? $this->fields[$field] : array();
    }

    /**
     * @return string
     */
    public function scopeSql()
    {
        return $this->scopeSql;
    }

    /**
     * @return array
     */
    public function scopeBind()
    {
        return $this->scopeBind;
    }
}
