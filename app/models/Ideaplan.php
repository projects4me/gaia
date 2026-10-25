<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Idea plan Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Ideaplan extends Model
{
    /**
     * Save in a transaction, so the idea row that planVersionBehavior locks
     * while numbering stays locked until the plan is stored. A save inside
     * a caller's transaction joins it.
     *
     * @return bool
     * @throws \Throwable
     */
    public function save(): bool
    {
        $connection = $this->getWriteConnection();
        if ($connection->isUnderTransaction()) {
            return parent::save();
        }

        $connection->begin();
        try {
            $saved = parent::save();
        } catch (\Throwable $e) {
            $connection->rollback();
            throw $e;
        }

        if ($saved) {
            $connection->commit();
        } else {
            $connection->rollback();
        }

        return $saved;
    }
}
