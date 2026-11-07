<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

use Gaia\Exception\BadRequest;

/**
 * Reads the structure of a where string without being fooled by quoted values: finds the leaves (innermost
 * parenthesised conditions), splits a value on a separator and unquotes a value.
 *
 * Quoting rules (agaia-4303):
 *  - a single quote opens a quoted value only at the start of a token (start of text, or after whitespace, "(" or
 *    ","), so an unquoted value such as O'Brien keeps today's meaning;
 *  - inside quotes, "''" and the legacy "\'" are an apostrophe, and "(", ")", "," and words are plain text;
 *  - a quote closes only when it is followed by the end, whitespace, ")" or ","; any other quote is plain text;
 *  - a quote that never closes is plain text, so unquoted values such as x' OR '1'='1 are read as before.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category Clause
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WhereTokenizer
{
    /**
     * The leaves of $where in order, each with its text (parentheses included) and byte offset. Blank groups
     * such as "()" are not leaves.
     *
     * @param string $where
     * @return array List of ['text' => string, 'offset' => int].
     * @throws BadRequest When the parentheses outside quotes are unbalanced.
     */
    public function leaves($where)
    {
        $where = (string) $where;
        $quoted = $this->quotedSpans($where);
        $open = [];
        $leaves = [];
        $length = strlen($where);

        for ($i = 0; $i < $length; $i++) {
            if (isset($quoted[$i])) {
                $i = $quoted[$i];
                continue;
            }
            if ($where[$i] === '(') {
                $open[] = ['offset' => $i, 'hasChild' => false];
            }
            elseif ($where[$i] === ')') {
                if (!$open) {
                    throw new BadRequest($this->unbalancedMessage('('));
                }
                $group = array_pop($open);
                if ($open) {
                    $open[count($open) - 1]['hasChild'] = true;
                }
                $text = substr($where, $group['offset'], $i - $group['offset'] + 1);
                if (!$group['hasChild'] && trim(substr($text, 1, -1)) !== '') {
                    $leaves[] = ['text' => $text, 'offset' => $group['offset']];
                }
            }
        }

        if ($open) {
            throw new BadRequest($this->unbalancedMessage(')'));
        }

        return $leaves;
    }

    /**
     * Split $text at each $separator found outside quotes. The parts are trimmed.
     *
     * @param string $text
     * @param string $separator e.g. "," or " AND " (matched case-sensitively).
     * @return array
     */
    public function split($text, $separator)
    {
        $text = (string) $text;
        $quoted = $this->quotedSpans($text);
        $parts = [];
        $start = 0;
        $length = strlen($text);
        $separatorLength = strlen($separator);

        for ($i = 0; $i < $length; $i++) {
            if (isset($quoted[$i])) {
                $i = $quoted[$i];
                continue;
            }
            if (substr_compare($text, $separator, $i, $separatorLength) === 0) {
                $parts[] = trim(substr($text, $start, $i - $start));
                $start = $i + $separatorLength;
                $i = $start - 1;
            }
        }
        $parts[] = trim(substr($text, $start));

        return $parts;
    }

    /**
     * The text of $token when the whole token is one quoted value, e.g. 'O''Brien' gives O'Brien.
     *
     * @param string $token
     * @return string|null Null when $token is not exactly one quoted value.
     */
    public function unquote($token)
    {
        $token = trim((string) $token);
        $quoted = $this->quotedSpans($token);

        if (count($quoted) !== 1 || !isset($quoted[0]) || $quoted[0] !== strlen($token) - 1) {
            return null;
        }

        return str_replace(["''", "\\'"], "'", substr($token, 1, -1));
    }

    /**
     * The quoted values in $text, as opening offset => closing offset. A quote that never closes is plain text.
     *
     * @param string $text
     * @return array
     */
    protected function quotedSpans($text)
    {
        $spans = [];
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            if ($text[$i] !== "'" || !$this->startsToken($text, $i)) {
                continue;
            }
            $opening = $i;
            $closing = null;
            for ($i = $opening + 1; $i < $length; $i++) {
                if ($text[$i] === '\\' && ($text[$i + 1] ?? '') === "'") {
                    $i++;
                }
                elseif ($text[$i] === "'" && ($text[$i + 1] ?? '') === "'") {
                    $i++;
                }
                elseif ($text[$i] === "'" && $this->endsToken($text, $i)) {
                    $closing = $i;
                    break;
                }
            }
            if ($closing === null) {
                $i = $opening;
                continue;
            }
            $spans[$opening] = $closing;
        }

        return $spans;
    }

    /**
     * Whether the character at $position starts a token.
     *
     * @param string $text
     * @param int $position
     * @return bool
     */
    protected function startsToken($text, $position)
    {
        return $position === 0 || ctype_space($text[$position - 1]) || in_array($text[$position - 1], ['(', ','], true);
    }

    /**
     * Whether the character at $position ends a token.
     *
     * @param string $text
     * @param int $position
     * @return bool
     */
    protected function endsToken($text, $position)
    {
        $next = $text[$position + 1] ?? '';
        return $next === '' || ctype_space($next) || in_array($next, [')', ','], true);
    }

    /**
     * @param string $missing The parenthesis the query lacks.
     * @return string
     */
    protected function unbalancedMessage($missing)
    {
        return 'Invalid query, please refer to the guides. Please check the parenthesis in the query. '
            . 'You have forgotten "' . $missing . '"';
    }
}
