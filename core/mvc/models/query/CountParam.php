<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

use Gaia\Exception\BadRequest;

/**
 * The `count` list parameter: `<field>` or `<field> AS <alias>`, e.g. `Issue.id AS issues`. It adds
 * `COUNT(<field>) AS <alias>` to the selected columns of a grouped list; the alias defaults to `total`.
 *
 * A count needs a `group` that includes the model's id (assertGroupedBy): a count beside ungrouped columns is not
 * valid SQL, and every listed record carries its id. That the field is known is checked by WhereFieldValidator.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category CountParam
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CountParam
{
    /**
     * Alias of the count column when the parameter names none.
     */
    const DEFAULT_ALIAS = 'total';

    /**
     * A field name (`field` or `alias.field`), optionally followed by `AS <alias>` in any case.
     */
    const PATTERN = '/^\s*((?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*$/i';

    /**
     * @var string The counted field, as written in the request.
     */
    private $field;

    /**
     * @var string Alias of the count column.
     */
    private $alias;

    /**
     * @param string $field
     * @param string $alias
     */
    public function __construct($field, $alias)
    {
        $this->field = $field;
        $this->alias = $alias;
    }

    /**
     * Parse the raw parameter.
     *
     * @param  mixed $raw The `count` request parameter.
     * @return CountParam
     * @throws BadRequest When it is not a field name optionally followed by AS and an alias
     */
    public static function parse($raw)
    {
        if (!is_string($raw) || !preg_match(self::PATTERN, $raw, $matches)) {
            $shown = is_string($raw) ? $raw : gettype($raw);
            throw new BadRequest(
                'Invalid count: "' . $shown . '" is not a field name, optionally followed by AS and an alias.'
            );
        }

        return new self($matches[1], !empty($matches[2]) ? $matches[2] : self::DEFAULT_ALIAS);
    }

    /**
     * @return string The counted field, as written in the request.
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return string Alias of the count column.
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Throw a BadRequest unless the `group` parameter includes the model's id (`<Model>.id` or `id`).
     *
     * @param  mixed  $groupBy    The `group` request parameter.
     * @param  string $modelAlias Alias of the listed model.
     * @return void
     * @throws BadRequest
     */
    public function assertGroupedBy($groupBy, $modelAlias)
    {
        $grouped = is_string($groupBy) ? array_map('trim', explode(',', $groupBy)) : [];
        if (!in_array($modelAlias . '.id', $grouped, true) && !in_array('id', $grouped, true)) {
            throw new BadRequest(
                'Invalid count: count needs a group that includes ' . $modelAlias . '.id, e.g. group='
                . $modelAlias . '.id.'
            );
        }
    }

    /**
     * The PHQL column that counts the field.
     *
     * @return string
     */
    public function toColumn()
    {
        return 'COUNT(' . $this->field . ') AS ' . $this->alias;
    }
}
