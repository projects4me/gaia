<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Agents;

use Gaia\MVC\Models\User;

/**
 * Rules the user endpoints apply to agent users: `users.type` is set only
 * when an agent is created through {@see AgentLifecycle} (so every Agent user
 * has an agents row), and an agent user never gets a password.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Agents
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentUserPolicy
{
    /**
     * A user created through the user endpoints is a Human.
     *
     * @param  array $attributes
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertCreatable(array $attributes)
    {
        if (array_key_exists('type', $attributes)
            && strcasecmp((string) $attributes['type'], User::TYPE_HUMAN) !== 0
        ) {
            $this->reject('Agents are created through the agent endpoint.');
        }
    }

    /**
     * The type of an existing user never changes, and an agent user gets no
     * password.
     *
     * @param  User  $user
     * @param  array $attributes
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertUpdatable(User $user, array $attributes)
    {
        if (array_key_exists('type', $attributes)
            && strcasecmp((string) $attributes['type'], (string) $user->type) !== 0
        ) {
            $this->reject('The type of a user cannot be changed.');
        }

        if (array_key_exists('password', $attributes)) {
            $this->assertPasswordSettable($user);
        }
    }

    /**
     * Reject setting a password on an agent user.
     *
     * @param  User $user
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertPasswordSettable(User $user)
    {
        if ($user->isAgent()) {
            $this->reject('An agent user cannot have a password.');
        }
    }

    /**
     * @param  string $message
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function reject($message)
    {
        throw new \Gaia\Exception\Permission($message);
    }
}
