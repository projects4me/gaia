<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\TransactionScope;
use Gaia\Libraries\Workflows\WorkflowNodeEdges;
use Gaia\Libraries\Workflows\WorkflowReferenceSync;

/**
 * Workflownode Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Workflownode extends Model
{
    /**
     * Save the node together with its workflow references, and on a delete
     * remove its edges too, in one transaction, so the references and edges
     * never disagree with the nodes. A save inside a caller's transaction
     * joins it.
     *
     * @return bool
     * @throws \Throwable
     */
    public function save(): bool
    {
        $work = function () {
            $deleted = !empty($this->isDeleting);
            return parent::save()
                && (new WorkflowReferenceSync())->syncNode($this, $deleted)
                && (!$deleted || (new WorkflowNodeEdges())->removeFor($this));
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
