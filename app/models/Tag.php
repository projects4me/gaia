<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Tags\TagLinks;
use Gaia\Libraries\Utils\TransactionScope;

/**
 * Tag Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Tag extends Model
{
    /**
     * On a delete, remove the tag from its records in the same transaction,
     * so no record keeps a link to a deleted tag (Tags D-003). A save inside
     * a caller's transaction joins it.
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
            return parent::save() && (new TagLinks())->removeAll($this->id);
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
