<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * The canonical semantic text of a record: the text that gets embedded, and its sha256. This is the only
 * implementation of the rule, so Gaia, Ares, the stale-write check and the reindex all agree on it:
 *
 * - the search block's semantic fields, in their order; null and empty fields are left out;
 * - each value with its HTML removed (tags, comments, script and style blocks) and its entities decoded;
 * - whitespace (Unicode spaces too) collapsed to one space and trimmed in each value;
 * - the values joined with a newline;
 * - cut to SEARCH_EMBED_MAX_CHARS characters (default 4000), trailing whitespace trimmed.
 *
 * The hash is the lower case hex sha256 of that text (64 characters). A record with no text has the text ''.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SemanticText
{
    /**
     * Characters the text is cut to when SEARCH_EMBED_MAX_CHARS is not set.
     *
     * @var int
     */
    public const DEFAULT_MAX_CHARS = 4000;

    /**
     * The semantic fields, in order.
     *
     * @var array
     */
    private $fields;

    /**
     * @var int
     */
    private $maxChars;

    /**
     * @param array $fields The semantic field names, in order
     * @param int|null $maxChars Characters to keep; null reads SEARCH_EMBED_MAX_CHARS
     */
    public function __construct(array $fields, $maxChars = null)
    {
        $this->fields = array_values($fields);
        $this->maxChars = $maxChars === null ? self::configuredMaxChars() : (int) $maxChars;
        if ($this->maxChars < 1) {
            throw new \Gaia\Exception\Exception('The semantic text needs a positive maximum length.');
        }
    }

    /**
     * This function returns the semantic text builder for a search block (as SearchBlock::apply left it).
     *
     * @param array $block
     * @return self
     */
    public static function forBlock(array $block)
    {
        return new self(isset($block['semantic']) ? $block['semantic'] : array());
    }

    /**
     * This function returns a record's canonical semantic text.
     *
     * @param array|object $record Field values, as an array or as a model's properties
     * @return string
     */
    public function text($record)
    {
        $parts = array();
        foreach ($this->fields as $field) {
            $part = self::clean(self::valueOf($record, $field));
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return rtrim(mb_substr(implode("\n", $parts), 0, $this->maxChars, 'UTF-8'));
    }

    /**
     * This function returns the sha256 of a record's canonical semantic text.
     *
     * @param array|object $record
     * @return string
     */
    public function hash($record)
    {
        return self::hashOf($this->text($record));
    }

    /**
     * This function returns the hash of a canonical semantic text.
     *
     * @param string $text
     * @return string 64 lower case hex characters
     */
    public static function hashOf($text)
    {
        return hash('sha256', $text);
    }

    /**
     * This function returns SEARCH_EMBED_MAX_CHARS, or the default when it is not a positive integer.
     *
     * @return int
     */
    public static function configuredMaxChars()
    {
        $configured = getenv('SEARCH_EMBED_MAX_CHARS');
        if (is_string($configured) && ctype_digit($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        return self::DEFAULT_MAX_CHARS;
    }

    /**
     * @param array|object $record
     * @param string $field
     * @return mixed
     */
    private static function valueOf($record, $field)
    {
        if (is_array($record)) {
            return isset($record[$field]) ? $record[$field] : null;
        }

        return isset($record->$field) ? $record->$field : null;
    }

    /**
     * This function turns one field value into plain text with single spaces.
     *
     * @param mixed $value
     * @return string
     */
    private static function clean($value)
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return '';
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $text = (string) $value;
        // Drop the content of blocks that are not text, then let every tag separate words.
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $text);
        $text = preg_replace('#<!--.*?-->#s', ' ', $text);
        $text = strip_tags(preg_replace('#<(?=[a-zA-Z/!?])#', ' <', $text));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Invalid UTF-8 would make the /u patterns fail: keep what is valid.
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $text));
    }
}
