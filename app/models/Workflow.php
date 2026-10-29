<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\TransactionScope;
use Gaia\Libraries\Workflows\WorkflowReferenceSync;

/**
 * Workflow Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Workflow extends Model
{
    /**
     * On a delete, remove the workflow's references in the same transaction,
     * so a deleted workflow is no longer listed for the users and agents it
     * named. A save inside a caller's transaction joins it.
     *
     * @return bool
     * @throws \Throwable
     */
    public function save(): bool
    {
        if (empty($this->isDeleting)) {
            return parent::save();
        }

        $work = function () {
            return parent::save() && (new WorkflowReferenceSync())->removeWorkflow($this);
        };

        $connection = $this->getWriteConnection();
        if ($connection->isUnderTransaction()) {
            return $work();
        }

        return (new TransactionScope($connection))->run($work, function ($saved) {
            return $saved;
        });
    }
}
