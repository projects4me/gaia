<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Looks up whether a live (not deleted) row exists for the records a
 * workflow names: projects in its scope, its owner, and the users and agents
 * in its nodes' config.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveRecords
{
    /**
     * Models whose `deleted` column is a smallint; the others are boolean.
     */
    private const SMALLINT_DELETED = ['Project', 'User'];

    /**
     * Whether a live row of the model (`Project`, `User`, `Agent`) has this id.
     *
     * @param  string $model
     * @param  string $id
     * @return bool
     */
    public function exists($model, $id)
    {
        $modelClass = '\\Gaia\\MVC\\Models\\' . $model;
        $live = in_array($model, self::SMALLINT_DELETED, true) ? 'deleted = 0' : 'deleted = false';

        return (bool) $modelClass::findFirst([
            'conditions' => 'id = :id: AND ' . $live,
            'bind' => ['id' => (string) $id],
        ]);
    }
}
