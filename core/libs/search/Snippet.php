<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * A result's snippet: a ts_headline of the query over the display snippet field, one short fragment around the
 * matches (the field's start when nothing matches), as HTML: the record's text escaped, the matches in <mark>.
 *
 * ts_headline does not escape the text, so it marks the matches with control characters that toHtml() turns into
 * <mark> after escaping.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Snippet
{
    /**
     * The marks ts_headline puts around a match: STX and ETX. They are stripped from the text first, so a record
     * cannot forge a mark.
     *
     * @var string
     */
    private const START = "\x02";
    private const STOP = "\x03";

    /**
     * @var TextSearchQuery
     */
    private $tsquery;

    /**
     * @param TextSearchQuery $tsquery
     */
    public function __construct(TextSearchQuery $tsquery)
    {
        $this->tsquery = $tsquery;
    }

    /**
     * This function returns the headline expression over a column, with its parameters.
     *
     * @param SearchTarget $target
     * @param string $column The quoted column
     * @param string $query
     * @return array ['sql' => string, 'bind' => array]
     */
    public function expression(SearchTarget $target, $column, $query)
    {
        return array(
            'sql' => 'ts_headline(' . $this->tsquery->config()
                . ', translate(' . $column . ', :search_marks, \'\'), '
                . $this->tsquery->sql() . ', :search_headline)',
            'bind' => $this->tsquery->bind($target, $query) + array(
                'search_marks' => self::START . self::STOP,
                'search_headline' => 'MaxFragments=1, MaxWords=30, MinWords=10, StartSel=' . self::START
                    . ', StopSel=' . self::STOP,
            ),
        );
    }

    /**
     * This function turns a headline into HTML: the text escaped, the matches in <mark>.
     *
     * @param string|null $headline
     * @return string|null
     */
    public function toHtml($headline)
    {
        if ($headline === null) {
            return null;
        }
        $html = htmlspecialchars($headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return strtr($html, array(self::START => '<mark>', self::STOP => '</mark>'));
    }
}
