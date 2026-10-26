<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Defines a new api-tester auth profile in every place at once, so it is never half-defined:
 * the user, role, user_role and permission rows in the harness seed script, and the
 * `authProfiles` entry in the fixtures JSON (the committed file and the copy the seed script
 * writes). Works on file texts and returns the new texts; the caller writes them.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProfileScaffold
{
    /**
     * The seed rows go in front of this line, inside the seed transaction.
     */
    private const SQL_ANCHOR = "-- Keep serial in sync after seeding explicit issueNumber values\n";

    /**
     * End of the `authProfiles` object, in the fixtures JSON and in the script's copy of it.
     */
    private const FIXTURE_ANCHOR = "\n    }\n  },\n  \"values\": {";

    /**
     * The test password every seeded api-tester user shares.
     */
    private const PASSWORD = 'unit-testing';

    private const ADMIN = 'api-test-user-0001';

    /**
     * @param string $name profile name, e.g. aclReports
     * @param string $role role name
     * @param string $permissionList `resource.action=0|1|2,...` (0 deny, 1 allow, 2 project members only)
     * @param string $harness the prepare-test-db.sh text
     * @param string $fixtures the fixtures/default.json text
     * @return string[] the new [harness, fixtures] texts
     * @throws ScaffoldException
     */
    public function define(string $name, string $role, string $permissionList, string $harness, string $fixtures): array
    {
        if (!preg_match('/^[a-z][A-Za-z0-9]*$/', $name)) {
            throw new ScaffoldException("Profile name '{$name}' must be camelCase letters and digits, e.g. aclReports");
        }
        if (trim($role) === '') {
            throw new ScaffoldException('--role=<role name> is required with --new-profile');
        }
        $permissions = $this->permissions($permissionList);
        $harness = str_replace("\r\n", "\n", $harness);
        $fixtures = str_replace("\r\n", "\n", $fixtures);
        $code = strtolower(preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $name));
        $email = 'api-tester-' . $code . '@example.com';

        if (strpos($fixtures, '"' . $name . '": {') !== false || strpos($harness, $email) !== false) {
            throw new ScaffoldException("Profile '{$name}' already exists");
        }
        if (!preg_match("/'(\\\$2y\\\$[^']+)'/", $harness, $hash)) {
            throw new ScaffoldException('prepare-test-db.sh has no seeded password hash to reuse');
        }
        if (substr_count($harness, self::SQL_ANCHOR) !== 1) {
            throw new ScaffoldException('prepare-test-db.sh lacks the "Keep serial in sync" anchor the seed rows go before');
        }

        $sql = $this->sql($code, $role, $email, $name, $hash[1], $permissions);
        $harness = str_replace(self::SQL_ANCHOR, $sql . self::SQL_ANCHOR, $harness);

        return [$this->withEntry($harness, $name, $email, 'prepare-test-db.sh'), $this->withEntry($fixtures, $name, $email, 'fixtures/default.json')];
    }

    /**
     * @param string $list
     * @return int[] resource.action => 0|1|2
     * @throws ScaffoldException
     */
    private function permissions(string $list): array
    {
        $permissions = [];
        foreach (array_filter(explode(',', $list)) as $item) {
            if (!preg_match('/^([a-z]+\.[a-z]+)=([012])$/', $item, $m)) {
                throw new ScaffoldException("Permission '{$item}' must look like issue.get=1 (0 deny, 1 allow, 2 members only)");
            }
            $permissions[$m[1]] = (int) $m[2];
        }
        if ($permissions === []) {
            throw new ScaffoldException('--permissions=<resource.action=0|1|2,...> is required with --new-profile');
        }

        return $permissions;
    }

    /**
     * @param string $code
     * @param string $role
     * @param string $email
     * @param string $name
     * @param string $hash
     * @param int[] $permissions
     * @return string
     * @throws ScaffoldException
     */
    private function sql(string $code, string $role, string $email, string $name, string $hash, array $permissions): string
    {
        $userId = 'api-test-user-' . $code;
        $roleId = 'api-test-role-' . $code;
        $roleName = str_replace("'", "''", $role);
        $audit = "'" . self::ADMIN . "', '" . self::ADMIN . "'";

        $rows = [];
        foreach ($permissions as $resource => $allowed) {
            $id = 'atp-acl-' . $code . '-' . str_replace('.', '-', $resource);
            if (strlen($id) > 36) {
                throw new ScaffoldException("Permission id {$id} is longer than the 36 characters the column holds; use a shorter profile name");
            }
            $rows[] = "  ('{$id}', '{$roleId}', '{$resource}', {$allowed}, NOW(), NOW())";
        }

        return "-- Profile {$name} (gaia-dev scaffold api-cases --new-profile)\n"
            . "INSERT INTO users (\n"
            . "  id, password, email, name, deleted, \"createdUser\", \"modifiedUser\",\n"
            . "  \"createdUserName\", \"modifiedUserName\", \"accountStatus\", \"failedLoginAttempts\"\n"
            . ") VALUES (\n"
            . "  '{$userId}',\n  '{$hash}',\n  '{$email}',\n  'API Tester {$roleName}',\n  0,\n"
            . "  {$audit},\n  'API Tester',\n  'API Tester',\n  'active',\n  0\n"
            . ")\nON CONFLICT (id) DO UPDATE SET email = EXCLUDED.email, deleted = 0;\n\n"
            . "INSERT INTO roles (\n"
            . "  id, name, description, deleted, \"createdUser\", \"modifiedUser\", \"createdUserName\", \"modifiedUserName\"\n"
            . ") VALUES (\n"
            . "  '{$roleId}', '{$roleName}', '{$roleName}', 0,\n  {$audit}, 'API Tester', 'API Tester'\n"
            . ")\nON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, deleted = 0;\n\n"
            . "INSERT INTO user_roles (\n"
            . "  id, \"createdUser\", \"modifiedUser\", \"userId\", \"roleId\",\n  \"createdUserName\", \"modifiedUserName\"\n"
            . ") VALUES (\n"
            . "  'api-test-userrole-{$code}', {$audit},\n  '{$userId}', '{$roleId}', 'API Tester', 'API Tester'\n"
            . ")\nON CONFLICT (id) DO UPDATE SET \"userId\" = EXCLUDED.\"userId\", \"roleId\" = EXCLUDED.\"roleId\";\n\n"
            . "INSERT INTO permissions (id, \"roleId\", \"resourceName\", allowed, \"dateCreated\", \"dateModified\")\nVALUES\n"
            . implode(",\n", $rows) . ";\n\n";
    }

    /**
     * @param string $text a text holding the fixtures JSON layout
     * @param string $name
     * @param string $email
     * @param string $where for the error message
     * @return string the text with the profile added at the end of `authProfiles`
     * @throws ScaffoldException
     */
    private function withEntry(string $text, string $name, string $email, string $where): string
    {
        if (substr_count($text, self::FIXTURE_ANCHOR) !== 1) {
            throw new ScaffoldException("{$where} does not end its authProfiles object the way the scaffolder expects");
        }
        $entry = "\n    },\n    \"{$name}\": {\n      \"email\": \"{$email}\",\n      \"password\": \"" . self::PASSWORD . "\"\n    }\n  },\n  \"values\": {";

        return str_replace(self::FIXTURE_ANCHOR, $entry, $text);
    }
}
