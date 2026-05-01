<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Phalcon\Mvc\Model\Resultset;

/**
 * Wraps a raw PDO cursor returned by Phalcon's $db->query() and streams rows
 * one at a time without buffering the entire dataset into memory.
 *
 * Each row is passed through denormalizeRow(), which converts the flat
 * ModelAlias_fieldname column format (produced by Query::getModelFields()
 * and Query::setRelatedFields()) into the same nested array structure that
 * Phalcon's Complex Resultset HYDRATE_ARRAYS produces:
 *
 *   Flat in  : ['Issue_id' => 1, 'Issue_name' => 'Bug', 'members_id' => 5, 'members_username' => 'john']
 *   Nested out: ['Issue' => ['id' => 1, 'name' => 'Bug'], 'members' => ['id' => 5, 'username' => 'john']]
 *
 * This makes ResultStream a drop-in replacement for Resultset inside
 * RestController::prepareData() — every step of the existing pipeline
 * (removeSecureFields, updateFields, applyACL, filterFieldsByACL,
 * flattenModelData) runs on ResultStream rows without modification.
 *
 * Simple queries (no joins) and Complex queries (with joins) are handled
 * through the exact same code path:
 *   - Simple : only base-model columns present → denormalized to ['Model' => [...]]
 *   - Complex: base-model + relationship columns → ['Model' => [...], 'rel' => [...]]
 *
 * NULL join rows (LEFT JOIN with no matching record) are handled naturally:
 * flattenModelData already guards hasMany appends with !empty($value['id']).
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Core\Mvc\Models
 * @category ResultStream
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResultStream implements \Iterator
{
    /**
     * Raw PDO cursor from Phalcon's $db->query().
     *
     * @var \Phalcon\Db\Result\Pdo
     */
    private $cursor;

    /**
     * Alias of the base model (e.g. "Issue", "User").
     * Used by denormalizeRow() to identify which prefix belongs to the base model.
     *
     * @var string
     */
    private string $modelAlias;

    /**
     * The current row (denormalized), or null when the cursor is exhausted.
     *
     * @var array|null
     */
    private ?array $current = null;

    /**
     * Zero-based position counter (satisfies the Iterator contract).
     *
     * @var int
     */
    private int $position = 0;

    /**
     * Guards against fetching the first row twice when rewind() is called.
     *
     * @var bool
     */
    private bool $started = false;

    /**
     * @param \Phalcon\Db\Result\Pdo $cursor     Raw PDO result from $db->query()
     * @param string                 $modelAlias Alias of the base model
     */
    public function __construct($cursor, string $modelAlias)
    {
        $this->cursor     = $cursor;
        $this->modelAlias = $modelAlias;
    }

    /**
     * No-op: mirrors the Resultset::setHydrateMode() call in prepareData()
     * so ResultStream can satisfy the same interface without modification.
     *
     * @param int $mode
     * @return void
     */
    public function setHydrateMode(int $mode): void
    {
    }

    /**
     * Rewinds the cursor to the first row.
     *
     * @return void
     */
    public function rewind(): void
    {
        if (!$this->started) {
            $this->started = true;
            $this->fetchNext();
        }
    }

    /**
     * Returns the current row.
     *
     * @return array|null
     */
    public function current(): ?array
    {
        return $this->current;
    }

    /**
     * Returns the current position.
     *
     * @return int
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Returns true if the current row is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->current !== null;
    }

    /**
     * Moves the cursor to the next row.
     *
     * @return void
     */
    public function next(): void
    {
        $this->fetchNext();
        $this->position++;
    }

    /**
     * Fetches the next raw row from the PDO cursor and denormalizes it.
     * Sets $this->current to null when no more rows are available.
     *
     * @return void
     */
    private function fetchNext(): void
    {
        $row = $this->cursor->fetch(\PDO::FETCH_ASSOC);

        $this->current = ($row !== false && $row !== null)
            ? $this->denormalizeRow($row)
            : null;
    }

    /**
     * Converts a flat ['Prefix_fieldname' => value] row into nested arrays.
     *
     * Every column alias follows the ModelAlias_fieldname convention because:
     *  - Base model fields are aliased by Query::getModelFields()
     *    e.g. Issue.id → Issue.id AS Issue_id
     *  - Relationship fields are aliased by Query::setRelatedFields()
     *    e.g. members.username → members.username AS members_username
     *
     * Splitting on the FIRST underscore is intentional and consistent with the
     * existing updateFields() logic in RestController. Model/relationship aliases
     * are CamelCase or camelCase identifiers and never contain underscores;
     * field names that contain underscores (e.g. created_at → created_at) are
     * preserved correctly because substr() captures everything after the first '_'.
     *
     * The base-model alias is intentionally left as a nested key — e.g.
     * ['Issue' => ['id' => 1, ...]]. flattenModelData() promotes it to the top
     * level, exactly as it does today with Phalcon Complex Resultset rows.
     *
     * @param  array $flatRow Flat associative row from PDO
     * @return array          Nested row matching Phalcon Complex Resultset format
     */
    private function denormalizeRow(array $flatRow): array
    {
        $nested = [];

        foreach ($flatRow as $colAlias => $value) {
            $sep = strpos($colAlias, '_');

            if ($sep !== false && $sep !== 0) {
                $prefix    = substr($colAlias, 0, $sep);
                $fieldName = substr($colAlias, $sep + 1);
                $nested[$prefix][$fieldName] = $value;
            } elseif ($sep !== false) {
                $field = substr($colAlias, $sep + 1);
                if (strpos($field, '_') !== false) {
                    $exploded = explode('_', $field);
                    $nested[$this->modelAlias][$exploded[0]][$exploded[1]] = $value;
                }
            } else {
                $nested[$this->modelAlias][$colAlias] = $value;
            }
        }

        return $nested;
    }
}
