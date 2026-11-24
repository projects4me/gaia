<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * What the keyword search looks for: every word of the query, where a word may also be written in one of its kept
 * alternative forms. It holds the query text as typed without the words that have alternatives (so tokens such as
 * config.php stay whole) and a group for each word (or pair of adjacent words, for a join) that has some.
 * TextSearchQuery turns it into a tsquery.
 *
 * A group is ['words' => its query words (one, or two for a join), 'forms' => the forms of each word, keyed by its
 * position in words: lists of words (one, or two for a split), 'joined' => the joined word or null].
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class KeywordQuery
{
    /**
     * @var string
     */
    private $text;

    /**
     * @var array
     */
    private $groups;

    /**
     * @param string $text The query text without the words that have alternatives
     * @param array $groups
     */
    private function __construct($text, array $groups)
    {
        $this->text = (string) $text;
        $this->groups = $groups;
    }

    /**
     * This function returns the keyword query of a text with no alternatives: exactly its words.
     *
     * @param string $text
     * @return KeywordQuery
     */
    public static function plain($text)
    {
        return new self($text, array());
    }

    /**
     * This function returns the keyword query of a query with the given kept alternatives. A join takes both its
     * words into one group; a join over a word an earlier join took is left out. The words with alternatives must
     * stand alone in the text (SearchQuery::standsAlone()), as QueryExpander proposes them.
     *
     * @param SearchQuery $query
     * @param array $kept Alternatives as QueryExpander::proposals() gives them
     * @return KeywordQuery
     */
    public static function expanded(SearchQuery $query, array $kept)
    {
        if ($kept === array()) {
            return self::plain($query->text());
        }

        $words = $query->words();
        $forms = array();
        $joins = array();
        foreach ($kept as $alternative) {
            if (count($alternative['at']) === 1) {
                $forms[$alternative['at'][0]][] = $alternative['form'];
            } else {
                $joins[] = $alternative;
            }
        }

        $groupOf = array();
        $groups = array();
        foreach ($joins as $join) {
            list($left, $right) = $join['at'];
            if (isset($groupOf[$left]) || isset($groupOf[$right])) {
                continue;
            }
            $groupOf[$left] = $groupOf[$right] = count($groups);
            $groups[] = array('at' => array($left, $right), 'joined' => $join['form'][0]);
        }
        foreach (array_keys($forms) as $at) {
            if (!isset($groupOf[$at])) {
                $groupOf[$at] = count($groups);
                $groups[] = array('at' => array($at), 'joined' => null);
            }
        }

        return new self($query->textWithout(array_keys($groupOf)), array_map(function ($group) use ($words, $forms) {
            $groupForms = array();
            foreach ($group['at'] as $at) {
                $groupForms[] = isset($forms[$at]) ? $forms[$at] : array();
            }

            return array(
                'words' => array_map(function ($at) use ($words) {
                    return $words[$at];
                }, $group['at']),
                'forms' => $groupForms,
                'joined' => $group['joined'],
            );
        }, $groups));
    }

    /**
     * This function returns the query text without the words that have alternatives.
     *
     * @return string
     */
    public function text()
    {
        return $this->text;
    }

    /**
     * This function returns the groups of words with alternatives.
     *
     * @return array
     */
    public function groups()
    {
        return $this->groups;
    }
}
