<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\TransactionScope;

/**
 * Issuelink Model: a typed link (blocks, relates, splits) from one issue to
 * another, in one project or across projects of one workspace (Workspaces
 * D-021).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Issuelink extends Model
{
    /**
     * Save in a transaction, so the issue graph lock that issueLinkBehavior
     * takes before checking the link holds until the link is stored. A save
     * inside a caller's transaction joins it.
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

        return (new TransactionScope($connection))->run(
            function () {
                return parent::save();
            },
            function ($saved) {
                return $saved === true;
            }
        );
    }
}
