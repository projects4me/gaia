<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * The query text of a search, parsed once and handed to every searcher: its words (runs of letters and digits,
 * lowercased), the words that are not stop words and the numbers, and its shape: a number alone (4170, #4170), a
 * reference (agaia-4170) or text, with the numbers written #N in it. Search has no query operators: quotes and a
 * leading dash are plain text.
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
     * A query that is a number alone, with an optional leading #.
     *
     * @var string
     */
    private const NUMBER_PATTERN = '/^#?([0-9]+)\z/';

    /**
     * A query that is a reference: a prefix (a project's short code), a dash and a number, e.g. agaia-4170.
     *
     * @var string
     */
    private const REFERENCE_PATTERN = '/^(\p{L}[\p{L}\p{N}]{0,49})-([0-9]+)\z/u';

    /**
     * A number written with a leading #, not inside a word, e.g. (see #3969).
     *
     * @var string
     */
    private const HASH_NUMBER_PATTERN = '/(?<![\p{L}\p{N}#])#([0-9]+)(?![\p{L}\p{N}])/u';

    /**
     * @var string
     */
    private $text;

    /**
     * @var array
     */
    private $words = array();

    /**
     * The text's runs of non-space characters, as typed.
     *
     * @var array
     */
    private $chunks;

    /**
     * Each word's chunk, by the word's position.
     *
     * @var array
     */
    private $chunkOf = array();

    /**
     * @param string $text The query text, trimmed and in bounds (SearchRequest)
     */
    public function __construct($text)
    {
        $this->text = (string) $text;
        $this->chunks = preg_split('/\s+/u', $this->text, -1, PREG_SPLIT_NO_EMPTY) ?: array();
        foreach ($this->chunks as $chunk => $chunkText) {
            foreach ($this->wordsOf($chunkText) as $word) {
                $this->chunkOf[count($this->words)] = $chunk;
                $this->words[] = $word;
            }
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
     * This function returns the words a typo can be told from: of three letters or more, not stop words, not numbers.
     *
     * @return array
     */
    public function typoCandidates()
    {
        return array_values(array_filter($this->nonStopWords(), function ($word) {
            return mb_strlen($word, 'UTF-8') >= 3 && !ctype_digit($word);
        }));
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
     * This function returns the digits of a query that is a number alone (4170, #4170), or null.
     *
     * @return string|null
     */
    public function number()
    {
        return preg_match(self::NUMBER_PATTERN, $this->text, $match) ? $match[1] : null;
    }

    /**
     * This function returns the prefix and the digits of a query that is a reference (agaia-4170), or null. The
     * prefix is as typed: whether it is a known one is up to the searcher.
     *
     * @return array|null ['prefix' => string, 'number' => string]
     */
    public function reference()
    {
        if (!preg_match(self::REFERENCE_PATTERN, $this->text, $match)) {
            return null;
        }

        return array('prefix' => $match[1], 'number' => $match[2]);
    }

    /**
     * This function returns the numbers written with a leading # (see #3969), in query order.
     *
     * @return array
     */
    public function hashNumbers()
    {
        preg_match_all(self::HASH_NUMBER_PATTERN, $this->text, $matches);

        return $matches[1];
    }

    /**
     * This function tells whether the query has several different words that are not stop words, so a record
     * matching all of them is a stronger match than one matching some.
     *
     * @return bool
     */
    public function hasSeveralWords()
    {
        return count(array_unique($this->nonStopWords())) >= 2;
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
     * This function tells whether the word at a position stands alone in the text: its run of non-space characters
     * holds no other word (quotes around it are fine), so it is not part of a token such as config.php or 1.2.3.
     *
     * @param int $at
     * @return bool
     */
    public function standsAlone($at)
    {
        return isset($this->chunkOf[$at]) && count(array_keys($this->chunkOf, $this->chunkOf[$at], true)) === 1;
    }

    /**
     * This function returns the text as typed without the words at the given positions, which must stand alone.
     *
     * @param array $positions
     * @return string
     */
    public function textWithout(array $positions)
    {
        $left = $this->chunks;
        foreach ($positions as $at) {
            unset($left[$this->chunkOf[$at]]);
        }

        return implode(' ', $left);
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
