<?php

namespace ApiTester;

class Runner
{
    private $baseUri;
    private $apisPath;
    private $fixturesPath;
    private $reportPath;
    private $filter;
    private $client;
    private $fixtures;
    private $asserter;
    private $token;
    private $tokens = [];

    public function __construct($baseUri, $apisPath, $fixturesPath, $reportPath, $filter = null)
    {
        $this->baseUri = $baseUri;
        $this->apisPath = $apisPath;
        $this->fixturesPath = $fixturesPath;
        $this->reportPath = $reportPath;
        $this->filter = $filter;
        $this->client = new HttpClient($baseUri);
        $this->fixtures = new Fixtures($fixturesPath);
        $this->asserter = new Asserter();
    }

    public function run()
    {
        $apisDoc = json_decode(file_get_contents($this->apisPath), true);
        if (!is_array($apisDoc) || !isset($apisDoc['apis']) || !is_array($apisDoc['apis'])) {
            fwrite(STDERR, "Invalid APIs file: {$this->apisPath}\n");
            return 1;
        }

        // Unique per suite run so unique-constrained fields (e.g. tags.tag) survive
        // soft-deleted leftovers from prior runs without requiring DB re-seed.
        $this->fixtures->setValue('runId', bin2hex(random_bytes(4)));

        $results = [];
        $passed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($apisDoc['apis'] as $api) {
            $id = isset($api['id']) ? $api['id'] : ($api['method'] . ' ' . $api['path']);
            if ($this->filter !== null && $this->filter !== '') {
                $haystack = $id . ' ' . (isset($api['path']) ? $api['path'] : '') . ' ' . (isset($api['resource']) ? $api['resource'] : '');
                $matched = false;
                foreach (preg_split('/\|/', $this->filter) as $term) {
                    $term = trim($term);
                    if ($term !== '' && stripos($haystack, $term) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }

            if (isset($api['enabled']) && $api['enabled'] === false) {
                $skipped++;
                $results[] = [
                    'id' => $id,
                    'status' => 'skipped',
                    'reason' => isset($api['skipReason']) ? $api['skipReason'] : 'disabled',
                ];
                echo "SKIP {$id}\n";
                continue;
            }

            try {
                $caseResult = $this->runCase($api);
            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'id' => $id,
                    'status' => 'failed',
                    'failures' => [$e->getMessage()],
                ];
                echo "FAIL {$id}\n  - {$e->getMessage()}\n";
                continue;
            }

            if ($caseResult['status'] === 'passed') {
                $passed++;
                echo "PASS {$id}\n";
            } elseif ($caseResult['status'] === 'skipped') {
                $skipped++;
                echo "SKIP {$id}\n";
            } else {
                $failed++;
                echo "FAIL {$id}\n";
                foreach ($caseResult['failures'] as $failure) {
                    echo "  - {$failure}\n";
                }
            }
            $results[] = $caseResult;
        }

        $report = [
            'generatedAt' => gmdate('c'),
            'baseUri' => $this->baseUri,
            'apisFile' => $this->apisPath,
            'fixturesFile' => $this->fixturesPath,
            'summary' => [
                'passed' => $passed,
                'failed' => $failed,
                'skipped' => $skipped,
                'totalExecuted' => $passed + $failed,
            ],
            'results' => $results,
        ];

        $dir = dirname($this->reportPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        echo "\nSummary: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
        echo "Report: {$this->reportPath}\n";

        return $failed > 0 ? 1 : 0;
    }

    private function runCase(array $api)
    {
        $id = isset($api['id']) ? $api['id'] : ($api['method'] . ' ' . $api['path']);
        $method = strtoupper($api['method']);
        $path = $this->fixtures->resolve($api['path']);

        if (strpos($path, '{') !== false) {
            return [
                'id' => $id,
                'status' => 'skipped',
                'reason' => 'Unresolved path placeholders; update fixtures values',
                'request' => ['method' => $method, 'path' => $path],
            ];
        }

        $options = [];
        if (!empty($api['query'])) {
            $options['query'] = $this->fixtures->resolve($api['query']);
        }
        if (isset($api['body'])) {
            $options['json'] = $this->fixtures->resolve($api['body']);
        }

        $needsAuth = !isset($api['auth']) || $api['auth'] !== false;
        if ($needsAuth) {
            $authProfile = null;
            if (isset($api['auth']) && is_string($api['auth']) && $api['auth'] !== '') {
                $authProfile = $api['auth'];
            }
            $token = $this->getToken($authProfile);
            $options['headers'] = ['Authorization' => 'Bearer ' . $token];
        }

        // Token endpoint special form body for oauth
        if ($api['resource'] === 'token' && $method === 'POST') {
            $auth = $this->fixtures->authConfig();
            if ($auth['clientSecret'] === '' || $auth['email'] === '' || $auth['password'] === '') {
                return [
                    'id' => $id,
                    'status' => 'skipped',
                    'reason' => 'Missing auth env/fixtures for token request',
                ];
            }
            $options = [
                'form' => [
                    'grant_type' => 'password',
                    'client_id' => $auth['clientId'],
                    'client_secret' => $auth['clientSecret'],
                    'email' => $auth['email'],
                    'password' => $auth['password'],
                ],
            ];
        }

        $response = $this->client->request($method, $path, $options);
        $expects = isset($api['expects']) ? $api['expects'] : ['status' => 200, 'shape' => 'json'];
        $expects = $this->fixtures->resolve($expects);
        $failures = $this->asserter->assert($expects, $response);

        if (empty($failures) && !empty($api['store'])) {
            $this->storeRuntimeValues($api['store'], $response);
        }

        return [
            'id' => $id,
            'status' => empty($failures) ? 'passed' : 'failed',
            'request' => [
                'method' => $method,
                'url' => $response['url'],
            ],
            'response' => [
                'status' => $response['status'],
                'bodyPreview' => $this->preview($response['body']),
            ],
            'expects' => $expects,
            'failures' => $failures,
        ];
    }

    /**
     * Persist values from a successful response into fixtures for later cases.
     *
     * Supported selectors:
     * - data.id
     * - data.attributes.<field>
     *
     * Example:
     *   "store": { "runtime.milestoneId": "data.id" }
     */
    private function storeRuntimeValues(array $storeMap, array $response)
    {
        $json = isset($response['json']) ? $response['json'] : null;
        if (!is_array($json)) {
            return;
        }

        foreach ($storeMap as $fixtureKey => $selector) {
            $value = $this->extractBySelector($json, $selector);
            if (is_string($value)) {
                // Some CHAR columns (e.g. savedsearch id/projectId) return padded values.
                $value = trim($value);
            }
            if ($value === null || $value === '') {
                throw new \RuntimeException(
                    "Unable to store '{$fixtureKey}' from selector '{$selector}'"
                );
            }
            $this->fixtures->setValue($fixtureKey, $value);
        }
    }

    private function extractBySelector(array $json, $selector)
    {
        if ($selector === 'data.id') {
            return isset($json['data']['id']) ? $json['data']['id'] : null;
        }
        if (strpos($selector, 'data.attributes.') === 0) {
            $field = substr($selector, strlen('data.attributes.'));
            return isset($json['data']['attributes'][$field]) ? $json['data']['attributes'][$field] : null;
        }
        return null;
    }

    private function getToken($profile = null)
    {
        $cacheKey = ($profile === null || $profile === '') ? '__default__' : $profile;
        if (!empty($this->tokens[$cacheKey])) {
            return $this->tokens[$cacheKey];
        }

        // Keep legacy single-token cache in sync for default profile.
        if ($cacheKey === '__default__' && !empty($this->token)) {
            $this->tokens[$cacheKey] = $this->token;
            return $this->token;
        }

        $auth = $this->fixtures->authConfig($profile);
        if ($auth['clientSecret'] === '' || $auth['email'] === '' || $auth['password'] === '') {
            throw new \RuntimeException(
                'Auth required but missing API_TEST_CLIENT_SECRET / API_TEST_EMAIL / API_TEST_PASSWORD'
                . ($profile ? " (profile: {$profile})" : '')
            );
        }

        $response = $this->client->request(
            'POST',
            $auth['tokenPath'],
            [
                'form' => [
                    'grant_type' => 'password',
                    'client_id' => $auth['clientId'],
                    'client_secret' => $auth['clientSecret'],
                    'email' => $auth['email'],
                    'password' => $auth['password'],
                ],
            ]
        );

        if ($response['status'] !== 200 || empty($response['json']['access_token'])) {
            throw new \RuntimeException(
                'Unable to acquire access token (status ' . $response['status'] . '): ' .
                $this->preview($response['body'])
            );
        }

        $this->tokens[$cacheKey] = $response['json']['access_token'];
        if ($cacheKey === '__default__') {
            $this->token = $this->tokens[$cacheKey];
        }
        return $this->tokens[$cacheKey];
    }

    private function preview($body)
    {
        $body = (string) $body;
        if (strlen($body) <= 500) {
            return $body;
        }
        return substr($body, 0, 500) . '...';
    }
}
