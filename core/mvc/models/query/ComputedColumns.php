<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

/**
 * Maps a model's computed fields (metadata key `computed`, Workspaces D-013) onto a read query: each one is selected
 * as `(<expression>) AS <name>`, so a list gets every row's value in the same query. A field is named in a request
 * as `<name>` or `<Model>.<name>`; a sort on it orders by the column alias.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category ComputedColumns
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ComputedColumns
{
    /**
     * @var string Alias of the queried model.
     */
    private $modelAlias;

    /**
     * @var array Computed field metadata by name; each has a PHQL `expression`.
     */
    private $computed;

    /**
     * @param string $modelAlias
     * @param array  $computed   Computed field metadata by name.
     */
    public function __construct($modelAlias, array $computed)
    {
        $this->modelAlias = $modelAlias;
        $this->computed = $computed;
    }

    /**
     * @return array Names of all computed fields.
     */
    public function names()
    {
        return array_keys($this->computed);
    }

    /**
     * Split the computed fields out of a requested field list.
     *
     * @param  array $fields Requested fields, e.g. `Workspace.name`, `projectCount`.
     * @return array [fields without the computed ones, names of the computed ones]
     */
    public function extract(array $fields)
    {
        $remaining = [];
        $names = [];
        foreach ($fields as $field) {
            $name = is_string($field) ? $this->nameOf(trim($field)) : null;
            if ($name === null) {
                $remaining[] = $field;
            } elseif (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return [$remaining, $names];
    }

    /**
     * The computed fields an ORDER BY clause names.
     *
     * @param  string|null $orderBy e.g. `Workspace.projectCount DESC`.
     * @return array
     */
    public function namedInOrderBy($orderBy)
    {
        $names = [];
        foreach ($this->orderByParts($orderBy) as $part) {
            $name = $this->nameOf(preg_replace('/\s+(ASC|DESC)$/i', '', trim($part)));
            if ($name !== null && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Rewrite an ORDER BY clause so a computed field is ordered by its column alias (`Workspace.projectCount DESC`
     * becomes `projectCount DESC`); other parts are kept as they are. A part naming a computed field that is not
     * selected (e.g. one the user may not read) is dropped, since its alias would not exist.
     *
     * @param  string|null $orderBy
     * @param  array|null  $selected Names of the selected computed fields; null means all of them.
     * @return string|null
     */
    public function rewriteOrderBy($orderBy, ?array $selected = null)
    {
        if (!is_string($orderBy) || $orderBy === '') {
            return $orderBy;
        }

        $parts = [];
        foreach ($this->orderByParts($orderBy) as $part) {
            $part = trim($part);
            if (preg_match('/^(\S+)(\s+(?:ASC|DESC))?$/i', $part, $matches)) {
                $name = $this->nameOf($matches[1]);
                if ($name !== null) {
                    if ($selected !== null && !in_array($name, $selected, true)) {
                        continue;
                    }
                    $part = $name . (isset($matches[2]) ? $matches[2] : '');
                }
            }
            $parts[] = $part;
        }

        return empty($parts) ? null : implode(', ', $parts);
    }

    /**
     * The select columns of the given computed fields.
     *
     * @param  array $names
     * @return array e.g. `(SELECT COUNT(...) ...) AS projectCount`
     */
    public function columns(array $names)
    {
        $columns = [];
        foreach ($names as $name) {
            if (isset($this->computed[$name]['expression'])) {
                $columns[] = '(' . $this->computed[$name]['expression'] . ') AS ' . $name;
            }
        }

        return $columns;
    }

    /**
     * The computed field a requested name points at, or null.
     *
     * @param  string $field `name` or `<Model>.name`.
     * @return string|null
     */
    private function nameOf($field)
    {
        $prefix = $this->modelAlias . '.';
        if (strpos($field, $prefix) === 0) {
            $field = substr($field, strlen($prefix));
        }

        return array_key_exists($field, $this->computed) ? $field : null;
    }

    /**
     * @param  string|null $orderBy
     * @return array
     */
    private function orderByParts($orderBy)
    {
        return is_string($orderBy) && $orderBy !== '' ? explode(',', $orderBy) : [];
    }
}
