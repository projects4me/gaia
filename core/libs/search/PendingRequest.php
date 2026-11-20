<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * One page request of the records whose embedding is pending: the keyset cursor `<type>:<id>` of the last record
 * of the previous page (none for the first page) and the page size, default DEFAULT_LIMIT and capped at MAX_LIMIT.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PendingRequest
{
    /**
     * The page size when the caller gives none, and the largest one served.
     *
     * @var int
     */
    public const DEFAULT_LIMIT = 200;
    public const MAX_LIMIT = 500;

    /**
     * A cursor: a search type name, a colon and a record id of up to 255 bytes without control characters. The type
     * holds no colon, so the id may.
     *
     * @var string
     */
    private const CURSOR = '/^([A-Za-z][A-Za-z0-9_]*):([^\x00-\x1f\x7f]{1,255})\z/';

    /**
     * @var string|null
     */
    private $afterType;

    /**
     * @var string
     */
    private $afterId = '';

    /**
     * @var int
     */
    private $limit;

    /**
     * @param mixed $after The cursor; null or '' for the first page
     * @param mixed $limit Null or '' for the default
     * @throws \Gaia\Exception\BadRequest When the cursor or the limit is malformed
     */
    public function __construct($after = null, $limit = null)
    {
        if ($after !== null && $after !== '') {
            if (!is_string($after) || !preg_match(self::CURSOR, $after, $matches)) {
                throw new \Gaia\Exception\BadRequest('The cursor must be the next value of a previous page.');
            }
            $this->afterType = $matches[1];
            $this->afterId = $matches[2];
        }

        if ($limit === null || $limit === '') {
            $limit = self::DEFAULT_LIMIT;
        } elseif (is_string($limit) && ctype_digit($limit)) {
            $limit = (int) $limit;
        }
        if (!is_int($limit) || $limit < 1) {
            throw new \Gaia\Exception\BadRequest('The limit must be a whole number from 1.');
        }
        $this->limit = min($limit, self::MAX_LIMIT);
    }

    /**
     * @param string $type
     * @param string $id
     * @return string The cursor that continues after this record
     */
    public static function cursor($type, $id)
    {
        return $type . ':' . $id;
    }

    /**
     * @return string|null The type of the last record of the previous page, null for the first page
     */
    public function afterType()
    {
        return $this->afterType;
    }

    /**
     * @return string The id of the last record of the previous page, '' for the first page
     */
    public function afterId()
    {
        return $this->afterId;
    }

    /**
     * @return int
     */
    public function limit()
    {
        return $this->limit;
    }
}
