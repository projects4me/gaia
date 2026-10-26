<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Seeds the api-tester harness for a new resource: the module in the admin permission list, seed rows,
 * the fixture ids (in the committed fixtures and the copy the harness writes) and the permission rows
 * of the profiles that get ACL cases.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceSeed
{
    private const SQL_ANCHOR = "-- Keep serial in sync after seeding explicit issueNumber values\n";
    private const MODULES_ANCHOR = "\n) AS m(module)";
    private const FIXTURE_ANCHOR = "    \"params.c_id\": ";
    private const ADMIN = 'api-test-user-0001';
    private const SAMPLES = ['text' => 'Seed', 'int' => '1', 'bool' => 'FALSE', 'float' => '1', 'decimal' => '1'];

    /**
     * @param ResourceSpec $spec
     * @param string $harness tools/api-tester/harness/prepare-test-db.sh
     * @param array $roles profile name => role id, for the profiles in the spec's ACL
     * @return string
     * @throws ScaffoldException
     */
    public function harness(ResourceSpec $spec, string $harness, array $roles): string
    {
        $harness = str_replace("\r\n", "\n", $harness);
        foreach ([self::SQL_ANCHOR, self::MODULES_ANCHOR] as $anchor) {
            if (substr_count($harness, $anchor) !== 1) {
                throw new ScaffoldException('prepare-test-db.sh lacks the anchor ' . trim($anchor));
            }
        }
        if (strpos($harness, "INSERT INTO {$spec->table} (") !== false) {
            throw new ScaffoldException("prepare-test-db.sh already seeds {$spec->table}");
        }
        $harness = str_replace(self::MODULES_ANCHOR, ", ('{$spec->resource}')" . self::MODULES_ANCHOR, $harness);
        $harness = $this->withFixtures($harness, $spec, 'prepare-test-db.sh', true);

        return str_replace(self::SQL_ANCHOR, $this->rows($spec) . $this->permissions($spec, $roles) . self::SQL_ANCHOR, $harness);
    }

    /**
     * @param ResourceSpec $spec
     * @param string $fixtures tools/api-tester/fixtures/default.json
     * @return string
     * @throws ScaffoldException
     */
    public function fixtures(ResourceSpec $spec, string $fixtures): string
    {
        return $this->withFixtures(str_replace("\r\n", "\n", $fixtures), $spec, 'fixtures/default.json', false);
    }

    /**
     * @param ResourceSpec $spec
     * @return string the fixture ids of the seed records: [id, other project's id]
     */
    public static function ids(ResourceSpec $spec): array
    {
        $base = 'api-test-' . $spec->resource . '-';

        return [$base . '000001', $base . '000002'];
    }

    /**
     * @param string $text
     * @param ResourceSpec $spec
     * @param string $where
     * @param bool $harness whether the text is the harness, which holds the fixtures in a heredoc
     * @return string
     * @throws ScaffoldException
     */
    private function withFixtures(string $text, ResourceSpec $spec, string $where, bool $harness): string
    {
        if (substr_count($text, self::FIXTURE_ANCHOR) !== 1) {
            throw new ScaffoldException("{$where} lacks the fixture anchor the ids go before");
        }
        list($first, $second) = self::ids($spec);
        $entries = "    \"{$spec->fixtureKey()}\": \"{$first}\",\n";
        if ($spec->parent !== null) {
            $entries .= "    \"otherProject" . ucfirst($spec->fixtureKey()) . "\": \"{$second}\",\n";
        }

        return str_replace(self::FIXTURE_ANCHOR, $entries . self::FIXTURE_ANCHOR, $text);
    }

    /**
     * @param ResourceSpec $spec
     * @return string the seed rows: one per project when the resource has a parent
     */
    private function rows(ResourceSpec $spec): string
    {
        $columns = ['id'];
        $parents = [];
        if ($spec->parent !== null) {
            $columns[] = '"' . $spec->parentKey() . '"';
            $parents = ['api-test-project-001', 'api-test-project-002'];
        }
        foreach ($spec->fields as $field) {
            $columns[] = '"' . $field['name'] . '"';
        }
        $columns = array_merge($columns, ['deleted', '"createdUser"', '"createdUserName"', '"modifiedUser"', '"modifiedUserName"', '"dateCreated"', '"dateModified"']);

        $rows = [];
        foreach (self::ids($spec) as $index => $id) {
            if ($spec->parent === null && $index > 0) {
                break;
            }
            $values = ["'{$id}'"];
            if ($spec->parent !== null) {
                $values[] = "'{$parents[$index]}'";
            }
            foreach ($spec->fields as $field) {
                $values[] = $this->sample($field);
            }
            $audit = "'" . self::ADMIN . "', 'API Tester', '" . self::ADMIN . "', 'API Tester', NOW(), NOW()";
            $rows[] = '  (' . implode(', ', $values) . ', FALSE, ' . $audit . ')';
        }

        return "-- {$spec->name}: seed records (gaia-dev scaffold resource)\n"
            . "INSERT INTO {$spec->table} (\n  " . implode(', ', $columns) . "\n) VALUES\n"
            . implode(",\n", $rows) . "\nON CONFLICT (id) DO UPDATE SET deleted = FALSE;\n\n";
    }

    /**
     * @param array $field
     * @return string a SQL literal that fits the field
     */
    private function sample(array $field): string
    {
        if ($field['default'] !== null) {
            return is_numeric($field['default']) ? $field['default'] : "'" . str_replace("'", "''", $field['default']) . "'";
        }
        if ($field['type'] === 'varchar') {
            return "'" . substr('Seed ' . $field['name'], 0, (int) $field['length']) . "'";
        }
        if ($field['type'] === 'datetime' || $field['type'] === 'date') {
            return 'NOW()';
        }
        $sample = self::SAMPLES[$field['type']];

        return $field['type'] === 'text' ? "'{$sample} {$field['name']}'" : $sample;
    }

    /**
     * @param ResourceSpec $spec
     * @param array $roles
     * @return string the permission rows for the ACL profiles, empty when there are none
     * @throws ScaffoldException
     */
    private function permissions(ResourceSpec $spec, array $roles): string
    {
        if ($spec->acl === []) {
            return '';
        }
        $rows = [];
        foreach ($spec->acl as $profile => $allowed) {
            $id = 'atp-' . strtolower(preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $profile)) . '-' . $spec->resource . '-get';
            if (strlen($id) > 36) {
                throw new ScaffoldException("Permission id {$id} is longer than the 36 characters the column holds");
            }
            $rows[] = "  ('{$id}', '{$roles[$profile]}', '{$spec->resource}.get', {$allowed}, NOW(), NOW())";
        }

        return "INSERT INTO permissions (id, \"roleId\", \"resourceName\", allowed, \"dateCreated\", \"dateModified\")\nVALUES\n"
            . implode(",\n", $rows) . ";\n\n";
    }
}
