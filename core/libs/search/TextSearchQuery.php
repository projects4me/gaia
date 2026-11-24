<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Db\Migration\PostgresSearchDdl;

/**
 * The query text as a Postgres tsquery (plainto_tsquery: every word, no operators, so quotes and a leading dash are
 * plain text), in the text search config the type's searchVector is built with, so the query is parsed the way the
 * records were.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TextSearchQuery
{
    /**
     * This function returns the SQL of the tsquery, over the parameters of bind().
     *
     * @return string
     */
    public function sql()
    {
        return 'plainto_tsquery(' . $this->config() . ', :search_fts)';
    }

    /**
     * This function returns the SQL of the text search config, over the parameters of bind().
     *
     * @return string
     */
    public function config()
    {
        return 'CAST(:search_config AS regconfig)';
    }

    /**
     * This function returns the parameters of the tsquery: the vector's config and the query text.
     *
     * @param SearchTarget $target
     * @param string $query
     * @return array
     */
    public function bind(SearchTarget $target, $query)
    {
        $vector = $target->field(SearchBlock::VECTOR);
        $config = isset($vector['generated']['config'])
            ? $vector['generated']['config']
            : PostgresSearchDdl::DEFAULT_TEXT_SEARCH_CONFIG;

        return array('search_config' => $config, 'search_fts' => $query);
    }
}
