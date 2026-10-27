<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

/**
 * Database fixtures shared by the agent tests: a human user to act as, and
 * removal of every agent the tests create (emails `unit-agent-%@gaia.test`).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentTestFixtures
{
    /**
     * Create or revive an Active human user.
     *
     * @param  string $id
     * @param  string $email
     * @return void
     */
    public static function ensureHumanUser($id, $email)
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute('DELETE FROM users WHERE id = ? OR email = ?', [$id, $email]);
        $db->execute(
            "INSERT INTO users (
                id, password, email, name, deleted,
                \"createdUser\", \"modifiedUser\", \"createdUserName\", \"modifiedUserName\",
                \"accountStatus\", \"failedLoginAttempts\", type
             ) VALUES (?, ?, ?, 'Agent Test Human', 0, ?, ?, 'testUser', 'testUser', 'Active', 0, 'Human')",
            [$id, password_hash('unit-testing', PASSWORD_DEFAULT), $email, $id, $id]
        );
    }

    /**
     * Hard-delete a user and its role assignments.
     *
     * @param  string $id
     * @return void
     */
    public static function deleteUser($id)
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute('DELETE FROM user_roles WHERE "userId" = ?', [$id]);
        $db->execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    /**
     * Hard-delete the agents, agent users and role assignments the tests created.
     *
     * @return void
     */
    public static function purge()
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $agentUsers = "SELECT id FROM users WHERE email LIKE 'unit-agent-%@gaia.test'";
        $db->execute("DELETE FROM user_roles WHERE \"userId\" IN ({$agentUsers})");
        $db->execute("DELETE FROM agents WHERE \"userId\" IN ({$agentUsers})");
        $db->execute("DELETE FROM users WHERE email LIKE 'unit-agent-%@gaia.test'");
    }
}
