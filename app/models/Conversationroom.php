<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

/**
 * Conversation room Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Conversationroom extends Model
{
    public function afterCreate()
    {
        if (!empty($this->issueId)){
            $issue = Issue::findFirst("id = '".$this->issueId."'");
            $issue->conversationRoomId = $this->newId;
            $issue->save();
        }
    }
}
