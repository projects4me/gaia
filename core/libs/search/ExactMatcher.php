<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Db\Migration\PostgresSearchDdl;

/**
 * Finds the records whose exact fields equal the query, e.g. an issue by its number. A numeric field matches only an
 * integer query, which may start with # (#123); a text field matches the query as it is (case-sensitive); a field of
 * any other type never matches. Their signal is 1; RankFusion pins them first.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ExactMatcher implements Searcher
{
    /**
     * The kind, as it appears in matchedBy.
     *
     * @var string
     */
    public const KIND = 'exact';

    /**
     * The largest value of each integer field type, so a query out of range is no match instead of a database error.
     *
     * @var array
     */
    private const INTEGER_TYPES = array(
        'tinyint' => 32767,
        'smallint' => 32767,
        'mediumint' => 2147483647,
        'int' => 2147483647,
        'integer' => 2147483647,
        'bigint' => PHP_INT_MAX,
    );

    /**
     * An integer query, with an optional leading #.
     *
     * @var string
     */
    private const INTEGER_PATTERN = '/^#?(\d{1,19})\z/';

    /**
     * @var CandidateQuery
     */
    private $candidates;

    /**
     * @param CandidateQuery $candidates
     */
    public function __construct(CandidateQuery $candidates)
    {
        $this->candidates = $candidates;
    }

    /**
     * {@inheritdoc}
     */
    public function kind()
    {
        return self::KIND;
    }

    /**
     * {@inheritdoc}
     */
    public function fields(SearchTarget $target)
    {
        return $target->block('exact');
    }

    /**
     * {@inheritdoc}
     */
    public function rank(SearchTarget $target, $query, $limit)
    {
        $conditions = array();
        $bind = array();
        foreach ($this->fields($target) as $i => $field) {
            $value = $this->valueFor($target->field($field), $query);
            if ($value === null) {
                continue;
            }
            $conditions[] = $this->candidates->column($field) . ' = :search_exact' . $i;
            $bind['search_exact' . $i] = $value;
        }
        if ($conditions === array()) {
            return array();
        }

        return $this->candidates->signals($target, implode(' OR ', $conditions), '1', null, $bind, $limit);
    }

    /**
     * This function returns the value a field is compared with, or null when the query cannot equal the field.
     *
     * @param array $schema The field's schema
     * @param string $query
     * @return int|string|null
     */
    private function valueFor(array $schema, $query)
    {
        $type = isset($schema['type']) ? $schema['type'] : '';
        if (in_array($type, PostgresSearchDdl::TEXT_SOURCE_TYPES, true)) {
            return $query;
        }
        if (!isset(self::INTEGER_TYPES[$type])) {
            return null;
        }
        if (!preg_match(self::INTEGER_PATTERN, $query, $match)) {
            return null;
        }
        $digits = ltrim($match[1], '0');
        $max = (string) self::INTEGER_TYPES[$type];
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            return null;
        }

        return (int) $digits;
    }
}
