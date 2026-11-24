<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Reads a search type's vocabulary (D-010) over a set of projects: in how many records a word, or an adjacent word
 * pair, occurs. Each record belongs to one project, so the sum over the projects is the exact count for that set; a
 * caller passes the projects the user may read, so the counts never tell of a record the user cannot see.
 *
 * The counts are as of the last rebuild (SearchVocabularyBuilder). Words are compared in lower case, as the builder
 * stores them. A type without a project column stores its counts under project '', so only a reader over every
 * project (null) sees them.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchVocabulary
{
    /**
     * @var string
     */
    private $table;

    /**
     * @var string[]|null
     */
    private $projectIds;

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface|null
     */
    private $db;

    /**
     * @param string $type The search type
     * @param string[]|null $projectIds The projects to count over; null counts over every project
     * @param \Phalcon\Db\Adapter\AdapterInterface|null $db Null uses the shared connection
     * @throws \Gaia\Exception\Exception When the type is not a search type
     */
    public function __construct($type, $projectIds, $db = null)
    {
        $this->table = VocabularySource::tableFor($type);
        $this->projectIds = $projectIds === null ? null : array_values(array_unique(array_map('strval', $projectIds)));
        $this->db = $db;
    }

    /**
     * This function returns the number of records a word occurs in.
     *
     * @param string $word
     * @return int
     */
    public function frequency($word)
    {
        return $this->count(self::normalize($word), '');
    }

    /**
     * This function returns the number of records in which word $b directly follows word $a.
     *
     * @param string $a
     * @param string $b
     * @return int
     */
    public function pairFrequency($a, $b)
    {
        $next = self::normalize($b);
        // next '' is the row of the word alone
        if ($next === '') {
            return 0;
        }

        return $this->count(self::normalize($a), $next);
    }

    /**
     * This function tells whether a word occurs in any record.
     *
     * @param string $word
     * @return bool
     */
    public function has($word)
    {
        return $this->frequency($word) > 0;
    }

    /**
     * @param string $term
     * @param string $next
     * @return int
     */
    private function count($term, $next)
    {
        if ($term === '' || $this->projectIds === array()) {
            return 0;
        }

        $sql = 'SELECT COALESCE(SUM(docs), 0) AS docs FROM "' . $this->table . '" WHERE term = ? AND next = ?';
        $bind = array($term, $next);
        if ($this->projectIds !== null) {
            $sql .= ' AND "projectId" IN (' . implode(', ', array_fill(0, count($this->projectIds), '?')) . ')';
            $bind = array_merge($bind, $this->projectIds);
        }
        $row = $this->db()->fetchOne($sql, \Phalcon\Db\Enum::FETCH_ASSOC, $bind);

        return (int) $row['docs'];
    }

    /**
     * This function reads a word as the builder stores it: in lower case, with the characters that are not letters or
     * digits around it dropped. Text that is more than one word gives '', which counts 0.
     *
     * @param string $word
     * @return string
     */
    private static function normalize($word)
    {
        $word = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $word, 'UTF-8')));

        return strpos($word, ' ') === false ? $word : '';
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
