<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

/**
 * Writes the column references in a piece of PHQL as bracketed identifiers, `Model.field` as
 * `[Model].[field]`, just before the PHQL reaches Phalcon's query builder.
 *
 * Phalcon 4.1's PHQL scanner strips a leading "not" from an unbracketed name after a dot
 * (`Mention.notified` is read as `ified`), so every column Gaia writes into PHQL is bracketed here.
 * The rest of the query layer (field ACL, where validation, relationship splitting) keeps reading the
 * plain `Model.field` form, which is also what clients send.
 *
 * Quoted text is never rewritten, and neither are bind placeholders (`:q0:`), `Model.*` or numbers.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PhqlIdentifiers
{
    /**
     * Quoted strings (kept as they are) or a plain `alias.field` reference.
     */
    const REFERENCE = '/(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")|(?<![\w\]\[:.\\\\$])([A-Za-z_]\w*)\.([A-Za-z_]\w*)(?![\w\[(.])/';

    /**
     * A trailing `AS alias` on a select column.
     */
    const COLUMN_ALIAS = '/\s+AS\s+([A-Za-z_]\w*)\s*$/i';

    /**
     * An order by or group by term that is a bare field name, with an optional direction.
     */
    const BARE_TERM = '/^(\s*)([A-Za-z_]\w*)(\s+(?:ASC|DESC))?\s*$/i';

    /**
     * Bracket every `alias.field` reference in a PHQL fragment (a where, order by, group by or having).
     *
     * @param  string|null $phql
     * @return string|null
     */
    public function bracket($phql)
    {
        if (!is_string($phql) || $phql === '') {
            return $phql;
        }

        return preg_replace_callback(self::REFERENCE, function ($match) {
            if (isset($match[1]) && $match[1] !== '') {
                return $match[1];
            }
            return '[' . $match[2] . '].[' . $match[3] . ']';
        }, $phql);
    }

    /**
     * Bracket each select column: its references and its trailing alias, so
     * `members.notes AS members_notes` becomes `[members].[notes] AS [members_notes]`.
     *
     * @param  array|string $columns
     * @return array|string
     */
    public function bracketColumns($columns)
    {
        if (!is_array($columns)) {
            return $this->bracketColumn($columns);
        }

        foreach ($columns as $key => $column) {
            $columns[$key] = $this->bracketColumn($column);
        }
        return $columns;
    }

    /**
     * Bracket one select column.
     *
     * @param  string $column
     * @return string
     */
    protected function bracketColumn($column)
    {
        if (!is_string($column)) {
            return $column;
        }

        $column = $this->bracket($column);
        return preg_replace(self::COLUMN_ALIAS, ' AS [$1]', $column);
    }

    /**
     * Bracket an order by or group by: each comma-separated term, where a term may also be a bare field
     * name with an optional direction (`notified DESC` becomes `[notified] DESC`).
     *
     * @param  array|string|null $terms
     * @return array|string|null
     */
    public function bracketTerms($terms)
    {
        if (is_array($terms)) {
            foreach ($terms as $key => $entry) {
                $terms[$key] = $this->bracketTerms($entry);
            }
            return $terms;
        }

        if (!is_string($terms) || $terms === '') {
            return $terms;
        }

        $parts = explode(',', $terms);
        foreach ($parts as $index => $part) {
            $parts[$index] = preg_match(self::BARE_TERM, $part)
                ? preg_replace(self::BARE_TERM, '$1[$2]$3', $part)
                : $this->bracket($part);
        }
        return implode(',', $parts);
    }
}
