<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * What a search returns: the results, best first, and the kinds of matching that were unavailable, so a caller can
 * tell a partial answer from a complete one.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchResults
{
    /**
     * @var array
     */
    private $items;

    /**
     * @var array
     */
    private $unavailable;

    /**
     * @param array $items Each ['type', 'id', 'title' (when readable), 'snippet' (when readable), 'score', 'matchedBy']
     * @param array $unavailable The kinds that could not run
     */
    public function __construct(array $items, array $unavailable)
    {
        $this->items = $items;
        $this->unavailable = $unavailable;
    }

    /**
     * @return array
     */
    public function items()
    {
        return $this->items;
    }

    /**
     * This function tells whether a kind of matching could not run, so its matches are missing.
     *
     * @param string $kind
     * @return bool
     */
    public function isUnavailable($kind)
    {
        return in_array($kind, $this->unavailable, true);
    }
}
