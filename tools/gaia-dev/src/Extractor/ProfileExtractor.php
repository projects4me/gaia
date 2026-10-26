<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

use RuntimeException;

/**
 * Extracts the api-tester auth profiles from the harness seed script
 * `tools/api-tester/harness/prepare-test-db.sh`: for each profile in
 * `fixtures/default.json` the user it logs in as, that user's role and the
 * permissions the role holds (`resource => allowed`, where 0 denies, 1 allows
 * and 2 allows members scope).
 *
 * The script is SQL inside shell, so this reads its INSERT statements with
 * patterns; it relies on the layout the script already uses (one `users`
 * insert with the email as third value, `roles`, `user_roles` and `permissions`
 * rows as value tuples, and the Admin grants as a modules x actions cross join).
 * Credentials are never read into the records.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProfileExtractor
{
    public const SOURCE = 'tools/api-tester/harness/prepare-test-db.sh';

    /**
     * The seed script does not name the Admin role it assigns to the main user, only its id.
     */
    private const ADMIN_ROLE = ['id' => '1', 'name' => 'Admin', 'description' => 'Admin role of the seeded main user'];

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return array[] per profile (the default profile first, then by name): name, user (id, email),
     *  role (id, name, description) and permissions (resource => allowed, sorted)
     */
    public function extract(): array
    {
        $script = $this->readScript();
        $users = $this->users($script);
        $roles = $this->roles($script);
        $assignments = $this->assignments($script);
        $permissions = $this->permissions($script);

        $profiles = [];
        foreach ($this->emails() as $name => $email) {
            if (!isset($users[$email])) {
                continue;
            }
            $userId = $users[$email];
            $roleId = $assignments[$userId] ?? null;
            $granted = $permissions[$roleId] ?? [];
            ksort($granted, SORT_STRING);
            $profiles[] = [
                'name' => $name,
                'user' => ['id' => $userId, 'email' => $email],
                'role' => $roleId === null ? null : ($roles[$roleId] ?? ['id' => $roleId, 'name' => null, 'description' => null]),
                'permissions' => $granted,
            ];
        }

        return $profiles;
    }

    /**
     * The script text without SQL comment lines.
     *
     * @return string
     */
    private function readScript(): string
    {
        $path = $this->root . '/' . self::SOURCE;
        if (!is_file($path)) {
            throw new RuntimeException("Source file not found: {$path}");
        }

        $script = str_replace("\r\n", "\n", file_get_contents($path));

        return preg_replace('/^\s*--.*$/m', '', $script);
    }

    /**
     * Profile name => login email, from the fixtures (the default profile first).
     *
     * @return string[]
     */
    private function emails(): array
    {
        $fixtures = SourceFile::readJson($this->root . '/' . FixtureExtractor::SOURCE);
        $emails = [];
        if (isset($fixtures['auth']['email'])) {
            $emails[FixtureExtractor::DEFAULT_PROFILE] = $fixtures['auth']['email'];
        }
        $profiles = isset($fixtures['authProfiles']) ? $fixtures['authProfiles'] : [];
        ksort($profiles, SORT_STRING);
        foreach ($profiles as $name => $profile) {
            if (isset($profile['email'])) {
                $emails[$name] = $profile['email'];
            }
        }

        return $emails;
    }

    /**
     * `INSERT INTO users` rows start (id, password, email): email => user id.
     *
     * @param string $script
     * @return string[]
     */
    private function users(string $script): array
    {
        preg_match_all("/\\(\\s*'([^']+)',\\s*'[^']*',\\s*'([^'@\\s]+@[^'\\s]+)'/", $this->statement($script, 'users'), $m, PREG_SET_ORDER);

        return array_column($m, 1, 2);
    }

    /**
     * @param string $script
     * @return array[] role id => id, name, description
     */
    private function roles(string $script): array
    {
        $roles = [self::ADMIN_ROLE['id'] => self::ADMIN_ROLE];
        preg_match_all("/\\(\\s*'([^']+)',\\s*'([^']*)',\\s*'([^']*)',\\s*\\d/", $this->statement($script, 'roles'), $m, PREG_SET_ORDER);
        foreach ($m as $row) {
            $roles[$row[1]] = ['id' => $row[1], 'name' => $row[2], 'description' => $row[3]];
        }

        return $roles;
    }

    /**
     * @param string $script
     * @return string[] user id => role id
     */
    private function assignments(string $script): array
    {
        preg_match_all(
            "/\\(\\s*'[^']+',\\s*'[^']+',\\s*'[^']+',\\s*'([^']+)',\\s*'([^']+)'/",
            $this->statement($script, 'user_roles'),
            $m,
            PREG_SET_ORDER
        );

        return array_column($m, 2, 1);
    }

    /**
     * The Admin grants (every module x every action) and the explicit permission rows.
     *
     * @param string $script
     * @return array[] role id => resource => allowed
     */
    private function permissions(string $script): array
    {
        $permissions = [];

        if (preg_match(
            "/VALUES((?:\\s*\\('[a-z]+'\\),?)+)\\s*\\)\\s*AS m\\(module\\)\\s*CROSS JOIN\\s*\\(\\s*VALUES((?:\\s*\\('[a-z]+'\\),?)+)/",
            $script,
            $cross
        )) {
            preg_match_all("/'([a-z]+)'/", $cross[1], $modules);
            preg_match_all("/'([a-z]+)'/", $cross[2], $actions);
            foreach ($modules[1] as $module) {
                foreach ($actions[1] as $action) {
                    $permissions[self::ADMIN_ROLE['id']][$module . '.' . $action] = 1;
                }
            }
        }

        preg_match_all(
            "/\\('atp-[^']+',\\s*'([^']+)',\\s*'([^']+)',\\s*(\\d)/",
            $script,
            $rows,
            PREG_SET_ORDER
        );
        foreach ($rows as $row) {
            $permissions[$row[1]][$row[2]] = (int) $row[3];
        }

        return $permissions;
    }

    /**
     * The value tuples of every `INSERT INTO <table> (` statement, up to each ON CONFLICT or semicolon.
     *
     * @param string $script
     * @param string $table
     * @return string
     */
    private function statement(string $script, string $table): string
    {
        if (!preg_match_all('/INSERT INTO ' . preg_quote($table, '/') . ' \\(.*?\\bVALUES\\b(.*?)(?:ON CONFLICT|;)/s', $script, $m)) {
            throw new RuntimeException(self::SOURCE . " has no INSERT INTO {$table}");
        }

        return implode("\n", $m[1]);
    }
}
