<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * The query text of a search, parsed once and handed to every searcher: its words (runs of letters and digits,
 * lowercased), the words that are not stop words, the numbers, the quoted phrases and the excluded (-word) words.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchQuery
{
    /**
     * The stop words of Postgres's english text search dictionary (english.stop), which FTS leaves out too.
     *
     * @var array
     */
    public const STOP_WORDS = array(
        'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours', 'yourself',
        'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers', 'herself', 'it', 'its', 'itself', 'they',
        'them', 'their', 'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those',
        'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having', 'do', 'does', 'did',
        'doing', 'a', 'an', 'the', 'and', 'but', 'if', 'or', 'because', 'as', 'until', 'while', 'of', 'at', 'by',
        'for', 'with', 'about', 'against', 'between', 'into', 'through', 'during', 'before', 'after', 'above',
        'below', 'to', 'from', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then',
        'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most',
        'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 's', 't',
        'can', 'will', 'just', 'don', 'should', 'now',
    );

    /**
     * @var string
     */
    private $text;

    /**
     * @var array
     */
    private $words;

    /**
     * @var array
     */
    private $phrases;

    /**
     * @var array
     */
    private $excluded;

    /**
     * @param string $text The query text, trimmed and in bounds (SearchRequest)
     */
    public function __construct($text)
    {
        $this->text = (string) $text;
        $this->words = $this->wordsOf($this->text);

        preg_match_all('/"([^"]*)"/u', $this->text, $phrases);
        $this->phrases = array_values(array_filter(array_map('trim', $phrases[1]), 'strlen'));

        preg_match_all('/(?:^|[\s\p{Z}])-([\p{L}\p{N}][^\s\p{Z}"]*)/u', $this->text, $excluded);
        $this->excluded = array();
        foreach ($excluded[1] as $word) {
            $this->excluded = array_merge($this->excluded, $this->wordsOf($word));
        }
    }

    /**
     * @return string
     */
    public function text()
    {
        return $this->text;
    }

    /**
     * This function returns the query's words, lowercased, in query order.
     *
     * @return array
     */
    public function words()
    {
        return $this->words;
    }

    /**
     * This function returns the words that are not stop words.
     *
     * @return array
     */
    public function nonStopWords()
    {
        return array_values(array_diff($this->words, self::STOP_WORDS));
    }

    /**
     * This function returns the words that are whole numbers.
     *
     * @return array
     */
    public function numbers()
    {
        return array_values(array_filter($this->words, 'ctype_digit'));
    }

    /**
     * This function returns the quoted phrases, without their quotes.
     *
     * @return array
     */
    public function phrases()
    {
        return $this->phrases;
    }

    /**
     * This function returns the words of the -word terms, lowercased.
     *
     * @return array
     */
    public function excluded()
    {
        return $this->excluded;
    }

    /**
     * This function tells whether the query has a letter or a digit, without which nothing can match it.
     *
     * @return bool
     */
    public function hasWords()
    {
        return $this->words !== array();
    }

    /**
     * This function returns how many letters and digits the words that are not stop words have.
     *
     * @return int
     */
    public function nonStopLength()
    {
        return mb_strlen(implode('', $this->nonStopWords()), 'UTF-8');
    }

    /**
     * @param string $text
     * @return array
     */
    private function wordsOf($text)
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $words);

        return array_map(function ($word) {
            return mb_strtolower($word, 'UTF-8');
        }, $words[0]);
    }
}
