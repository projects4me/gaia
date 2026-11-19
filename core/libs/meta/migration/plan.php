<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta\Migration;

/**
 * This class holds the statements a dry-run migration would execute, each with the
 * table (or view) it belongs to. The migration driver names the table before each
 * step; the planning connection adds the statements.
 *
 * A routine statement is one migrate runs on every pass even when nothing changed
 * (rebuilding a view that exists); it is listed but does not count as a change.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Meta\Migration
 * @category Migration
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Plan
{
    /**
     * The planned statements: each array('table' => string, 'sql' => string, 'routine' => bool)
     *
     * @var array
     */
    private $statements = array();

    /**
     * The table the next statements belong to
     *
     * @var string
     */
    private $table = '';

    /**
     * Whether the next statements are routine
     *
     * @var bool
     */
    private $routine = false;

    /**
     * This function names the table the following statements belong to.
     *
     * @param string $table
     * @param bool $routine Whether its statements run on every pass even without a change
     * @return void
     */
    public function forTable(string $table, bool $routine = false)
    {
        $this->table = $table;
        $this->routine = $routine;
    }

    /**
     * This function adds a statement for the current table.
     *
     * @param string $sql
     * @return void
     */
    public function add(string $sql)
    {
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }
        $this->statements[] = array('table' => $this->table, 'sql' => $sql, 'routine' => $this->routine);
    }

    /**
     * This function returns the planned statements that change the schema.
     *
     * @return array
     */
    public function changes(): array
    {
        return array_values(array_filter($this->statements, function ($statement) {
            return !$statement['routine'];
        }));
    }

    /**
     * This function returns the routine statements (run on every pass).
     *
     * @return array
     */
    public function routine(): array
    {
        return array_values(array_filter($this->statements, function ($statement) {
            return $statement['routine'];
        }));
    }

    /**
     * This function tells whether the plan changes the schema.
     *
     * @return bool
     */
    public function hasChanges(): bool
    {
        return count($this->changes()) > 0;
    }
}
