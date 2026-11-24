<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Db\Migration\PostgresSearchDdl;

/**
 * Finds the records whose exact fields equal the query, e.g. an issue by its number. A numeric field matches the
 * numbers the query refers to: the query when it is a number alone, which may start with # (#123); every number of
 * a query of up to numberQueryWords words (issue 4174, 4174 login); in a longer query, pasted text, only the numbers
 * written with # (see #3969). A reference query (agaia-4170) refers to no number here: ReferenceMatcher looks it up.
 * A text field matches the query as it is (case-sensitive); a field of any other type never matches. Their signal is
 * 1; StrongestSignal scores them 1 and puts them first.
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
     * @var CandidateQuery
     */
    private $candidates;

    /**
     * @var int
     */
    private $numberQueryWords;

    /**
     * @param CandidateQuery $candidates
     * @param int $numberQueryWords The most words a query may have for its plain numbers to be looked up, at least 1
     * @throws \Gaia\Exception\Exception When the word count is not a positive whole number
     */
    public function __construct(CandidateQuery $candidates, $numberQueryWords)
    {
        if (filter_var($numberQueryWords, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
            throw new \Gaia\Exception\Exception('The search number query words must be a whole number from 1.');
        }
        $this->candidates = $candidates;
        $this->numberQueryWords = (int) $numberQueryWords;
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
    public function rank(SearchTarget $target, SearchQuery $query, $limit)
    {
        $numbers = $this->referredNumbers($query);
        $conditions = array();
        $bind = array();
        foreach ($this->fields($target) as $i => $field) {
            $schema = $target->field($field);
            if (in_array(self::typeOf($schema), PostgresSearchDdl::TEXT_SOURCE_TYPES, true)) {
                $conditions[] = $this->candidates->column($field) . ' = :search_exact' . $i;
                $bind['search_exact' . $i] = $query->text();
                continue;
            }
            foreach ($numbers as $j => $digits) {
                $value = self::integerValue($schema, $digits);
                if ($value === null) {
                    continue;
                }
                $conditions[] = $this->candidates->column($field) . ' = :search_exact' . $i . '_' . $j;
                $bind['search_exact' . $i . '_' . $j] = $value;
            }
        }
        if ($conditions === array()) {
            return array();
        }

        return $this->candidates->signals($target, implode(' OR ', $conditions), '1', null, $bind, $limit);
    }

    /**
     * This function tells whether a field's schema is of an integer type.
     *
     * @param array $schema
     * @return bool
     */
    public static function isInteger(array $schema)
    {
        return isset(self::INTEGER_TYPES[self::typeOf($schema)]);
    }

    /**
     * This function returns the value an integer field is compared with for a run of digits, or null when the field
     * is not an integer or the number is out of its range, so the query is no match instead of a database error.
     *
     * @param array $schema The field's schema
     * @param string $digits
     * @return int|null
     */
    public static function integerValue(array $schema, $digits)
    {
        if (!self::isInteger($schema) || !ctype_digit((string) $digits)) {
            return null;
        }
        $digits = ltrim($digits, '0');
        $max = (string) self::INTEGER_TYPES[self::typeOf($schema)];
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * This function returns the numbers the query refers to, without repeats.
     *
     * @param SearchQuery $query
     * @return array Runs of digits
     */
    private function referredNumbers(SearchQuery $query)
    {
        if ($query->number() !== null) {
            return array($query->number());
        }
        if ($query->reference() !== null) {
            return array();
        }
        $numbers = count($query->words()) <= $this->numberQueryWords ? $query->numbers() : $query->hashNumbers();

        return array_values(array_unique($numbers));
    }

    /**
     * @param array $schema
     * @return string
     */
    private static function typeOf(array $schema)
    {
        return isset($schema['type']) ? $schema['type'] : '';
    }
}
