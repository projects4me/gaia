<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Check;

use GaiaDev\Query\GraphReader;

/**
 * Checks the api-tester catalogs against the graph: every resource a profile is restricted on
 * has acl cases, every placeholder of an enabled hand-written case resolves to a fixture, a stored runtime id or a value the
 * runner provides, and every assertion name is one the Asserter or Runner implements.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Check
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ApiCasesCheck
{
    /**
     * Placeholders the Runner sets itself (tools/api-tester/src/Runner.php).
     */
    private const RUNNER_VALUES = ['runId'];

    /**
     * Catalogs written by a generator: their placeholders are the generator's to fix (agaia follow-up), not an author's.
     */
    private const GENERATED_CATALOGS = ['backend'];

    /**
     * Edges that restrict a profile on a permission; allowing it everywhere needs no case.
     */
    private const RESTRICTIONS = ['denies', 'grants_members'];

    /**
     * @var GraphReader
     */
    private $graph;

    /**
     * @param GraphReader $graph
     */
    public function __construct(GraphReader $graph)
    {
        $this->graph = $graph;
    }

    /**
     * @param string|null $endpoint a resource name or a path prefix to limit the check to
     * @return string[] the problems, empty when the catalogs are consistent
     */
    public function problems(?string $endpoint = null): array
    {
        $cases = $this->cases($endpoint);

        return array_merge($this->aclCoverage($endpoint), $this->placeholders($cases), $this->assertions($cases));
    }

    /**
     * @param string|null $endpoint
     * @return array[] case nodes on the endpoint, or every case
     */
    private function cases(?string $endpoint): array
    {
        $cases = array_values($this->graph->nodes('case'));
        if ($endpoint === null) {
            return $cases;
        }
        $path = $endpoint !== '' && $endpoint[0] === '/' ? $endpoint : '/api/v1/' . $endpoint;

        return array_values(array_filter($cases, function ($case) use ($endpoint, $path) {
            return $case['attributes']['resource'] === $endpoint || strpos($case['attributes']['path'], $path) === 0;
        }));
    }

    /**
     * @param string|null $endpoint
     * @return string[]
     */
    private function aclCoverage(?string $endpoint): array
    {
        $covered = [];
        foreach ($this->graph->nodes('case') as $case) {
            if ($case['attributes']['catalog'] === 'acl') {
                $covered[$case['attributes']['resource']] = true;
            }
        }

        $problems = [];
        foreach ($this->graph->nodes('profile') as $profileKey => $profile) {
            if ($profileKey === 'default' || $profileKey === 'anonymous') {
                continue;
            }
            foreach (self::RESTRICTIONS as $edge) {
                foreach ($this->graph->out($profile, $edge) as $permission) {
                    $parts = explode('.', $permission['key']);
                    if (count($parts) !== 2 || $permission['attributes']['kind'] !== 'action') {
                        continue;
                    }
                    if (($endpoint !== null && $parts[0] !== $endpoint) || isset($covered[$parts[0]])) {
                        continue;
                    }
                    $problems[] = "acl: {$profileKey} is restricted on {$permission['key']} but no acl case covers resource {$parts[0]}";
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * @param array[] $cases
     * @return string[]
     */
    private function placeholders(array $cases): array
    {
        $stored = [];
        foreach ($this->graph->nodes('case') as $case) {
            foreach ($case['attributes']['stores'] as $key) {
                $stored[$key] = true;
            }
        }

        $problems = [];
        foreach ($cases as $case) {
            if (!$case['attributes']['enabled'] || in_array($case['attributes']['catalog'], self::GENERATED_CATALOGS, true)) {
                continue;
            }
            foreach ($case['attributes']['placeholders'] as $key) {
                $resolved = $this->graph->node('fixture', $key) !== null
                    || isset($stored[$key])
                    || in_array($key, self::RUNNER_VALUES, true)
                    || strpos($key, '(') !== false;
                if (!$resolved) {
                    $problems[] = "placeholder: {$case['key']} uses {{$key}}, which is no fixture, stored runtime id or runner value";
                }
            }
        }

        return $problems;
    }

    /**
     * @param array[] $cases
     * @return string[]
     */
    private function assertions(array $cases): array
    {
        $problems = [];
        foreach ($cases as $case) {
            foreach ($this->graph->out($case, 'asserts') as $assertion) {
                if (!isset($assertion['attributes']['source'])) {
                    $problems[] = "assertion: {$case['key']} asserts '{$assertion['key']}', which the Asserter does not implement";
                }
            }
        }

        return $problems;
    }
}
