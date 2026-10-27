<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Agent Model
 *
 * The runtime config of an AI agent; the agent's identity is its users row
 * (`userId`, users.type = Agent).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Model
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Agent extends Model
{
    /**
     * The live agent row of a user, or null when the user is not an agent.
     *
     * @param  string $userId
     * @return Agent|null
     */
    public static function findForUser($userId)
    {
        if ($userId === null || (string) $userId === '') {
            return null;
        }

        $agent = self::findFirst([
            'conditions' => 'userId = :userId: AND deleted = false',
            'bind' => ['userId' => (string) $userId],
        ]);

        return $agent ?: null;
    }
}
