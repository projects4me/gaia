<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\TransactionScope;

/**
 * Issue Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Issue extends Model
{
    /**
     * Sequence name for the issue model.
     *
     * @return string
     */
    public function getSequenceName()
    {
        return '"issues_issueNumber_seq"';
    }

    /**
     * Save in a transaction, so the issue graph lock that
     * issueParentCycleBehavior takes before checking parentId holds until the
     * issue is stored. A save inside a caller's transaction joins it.
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
