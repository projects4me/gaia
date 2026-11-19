<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * What a caller asks the search for: the types to search, the query text and how many results to return.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchRequest
{
    /**
     * Length bounds of the trimmed query text, in characters.
     *
     * @var int
     */
    public const MIN_QUERY_LENGTH = 2;
    public const MAX_QUERY_LENGTH = 200;

    /**
     * The limit when the caller gives none, and the largest one allowed.
     *
     * @var int
     */
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 50;

    /**
     * @var array
     */
    private $types;

    /**
     * @var string
     */
    private $query;

    /**
     * @var int
     */
    private $limit;

    /**
     * @param array $types Search types, as in the search blocks
     * @param mixed $query The query text
     * @param mixed $limit Null for the default
     * @throws \Gaia\Exception\BadRequest When a value is out of bounds
     */
    public function __construct(array $types, $query, $limit = null)
    {
        if ($types === array()) {
            throw new \Gaia\Exception\BadRequest('Give at least one search type.');
        }
        foreach ($types as $type) {
            if (!is_string($type) || $type === '') {
                throw new \Gaia\Exception\BadRequest('A search type must be a name.');
            }
        }
        $types = array_values(array_unique($types));

        if (!is_string($query) || !mb_check_encoding($query, 'UTF-8') || strpos($query, "\0") !== false) {
            throw new \Gaia\Exception\BadRequest('The search query must be UTF-8 text.');
        }
        // Unicode spaces too, so a query of no-break spaces is empty
        $query = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $query);
        $length = mb_strlen($query, 'UTF-8');
        if ($length < self::MIN_QUERY_LENGTH || $length > self::MAX_QUERY_LENGTH) {
            throw new \Gaia\Exception\BadRequest('The search query must be ' . self::MIN_QUERY_LENGTH . ' to '
                . self::MAX_QUERY_LENGTH . ' characters long.');
        }

        if ($limit === null || $limit === '') {
            $limit = self::DEFAULT_LIMIT;
        } elseif (is_string($limit) && ctype_digit($limit)) {
            $limit = (int) $limit;
        }
        if (!is_int($limit) || $limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \Gaia\Exception\BadRequest('The search limit must be a whole number from 1 to '
                . self::MAX_LIMIT . '.');
        }

        $this->types = $types;
        $this->query = $query;
        $this->limit = $limit;
    }

    /**
     * @return array
     */
    public function types()
    {
        return $this->types;
    }

    /**
     * @return string
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * @return int
     */
    public function limit()
    {
        return $this->limit;
    }
}
