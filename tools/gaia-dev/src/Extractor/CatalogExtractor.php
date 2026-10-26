<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

/**
 * Extracts the cases of the api-tester catalogs (`tools/api-tester/apis/*.json`:
 * backend, client, acl, live, mail): per case its endpoint, the auth profile it
 * runs as, the assertions it uses, the fixture placeholders it reads and the
 * runtime values it stores.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CatalogExtractor
{
    public const SOURCE_DIR = 'tools/api-tester/apis';

    /**
     * Profile name for cases that send no token (`"auth": false`).
     */
    public const ANONYMOUS_PROFILE = 'anonymous';

    /**
     * Same placeholder syntax as `ApiTester\Fixtures`: `{key}` or `${key}`.
     */
    private const PLACEHOLDER = '/\$?\{\s*([a-zA-Z0-9_.\(\)]+)\s*\}/';

    /**
     * Case keys whose sub-keys are assertions, with the sub-keys that are run
     * options rather than assertions.
     */
    private const ASSERTION_GROUPS = [
        'expects' => [],
        'hermes' => ['timeoutMs', 'requireUp'],
        'mail' => [],
    ];

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
     * @return array[] one record per catalog (sorted by name) with the keys
     *  catalog, file, endpoints and cases
     */
    public function extract(): array
    {
        $records = [];
        $files = glob($this->root . '/' . self::SOURCE_DIR . '/*.json');
        sort($files);

        foreach ($files as $file) {
            $records[] = $this->catalog($file);
        }

        return $records;
    }

    /**
     * @param string $file
     * @return array
     */
    private function catalog(string $file): array
    {
        $catalog = basename($file, '.json');
        $data = SourceFile::readJson($file);
        $cases = [];
        $endpoints = [];

        foreach (isset($data['apis']) ? $data['apis'] : [] as $case) {
            $record = $this->caseRecord($case);
            $cases[] = $record;
            $endpoints[$record['method'] . ' ' . $record['path']] = true;
        }

        $endpoints = array_keys($endpoints);
        sort($endpoints);

        return [
            'catalog' => $catalog,
            'file' => self::SOURCE_DIR . '/' . basename($file),
            'endpoints' => $endpoints,
            'cases' => $cases,
        ];
    }

    /**
     * @param array $case
     * @return array
     */
    private function caseRecord(array $case): array
    {
        return [
            'id' => $case['id'],
            'method' => strtoupper($case['method']),
            'path' => $case['path'],
            'resource' => isset($case['resource']) ? $case['resource'] : null,
            'profile' => $this->profile(isset($case['auth']) ? $case['auth'] : false),
            'enabled' => !isset($case['enabled']) || $case['enabled'] !== false,
            'source' => isset($case['source']) ? $case['source'] : null,
            'assertions' => $this->assertions($case),
            'placeholders' => $this->placeholders($case),
            'stores' => isset($case['store']) ? array_keys($case['store']) : [],
        ];
    }

    /**
     * @param bool|string $auth
     * @return string
     */
    private function profile($auth): string
    {
        if (is_string($auth) && $auth !== '') {
            return $auth;
        }

        return $auth ? FixtureExtractor::DEFAULT_PROFILE : self::ANONYMOUS_PROFILE;
    }

    /**
     * Assertion names are the keys under `expects`, plus `hermes.<key>` and
     * `mail.<key>` for the live and mail checks.
     *
     * @param array $case
     * @return string[]
     */
    private function assertions(array $case): array
    {
        $assertions = [];

        foreach (self::ASSERTION_GROUPS as $group => $options) {
            if (empty($case[$group]) || !is_array($case[$group])) {
                continue;
            }
            foreach (array_diff(array_keys($case[$group]), $options) as $key) {
                $assertions[] = $group === 'expects' ? $key : $group . '.' . $key;
            }
        }

        sort($assertions);

        return $assertions;
    }

    /**
     * Placeholder keys used anywhere in the case (path, query, body, expectations).
     *
     * @param array $case
     * @return string[]
     */
    private function placeholders(array $case): array
    {
        $found = [];

        array_walk_recursive($case, function ($value) use (&$found) {
            if (is_string($value) && preg_match_all(self::PLACEHOLDER, $value, $matches)) {
                foreach ($matches[1] as $key) {
                    $found[$key] = true;
                }
            }
        });

        $found = array_keys($found);
        sort($found);

        return $found;
    }
}
