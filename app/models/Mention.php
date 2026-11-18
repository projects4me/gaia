<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\MVC\REST\Controllers\Components\Events\MentionLiveEvents;

/**
 * Mention Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Mention extends Model
{
    /**
     * Tell the mentioned user as a domain event. Fails open: a publish
     * error never fails the save.
     *
     * @method afterCreate
     * @return void
     */
    public function afterCreate()
    {
        global $logger;

        try {
            (new MentionLiveEvents())->publishMentioned($this);
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Mention publish failed: ' . $e->getMessage());
            }
        }
    }
}
