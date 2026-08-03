<?php

namespace ApiTester;

class Fixtures
{
    private $data;
    private $values;

    public function __construct($path)
    {
        $raw = file_get_contents($path);
        $this->data = json_decode($raw, true);
        if (!is_array($this->data)) {
            throw new \RuntimeException('Invalid fixtures JSON: ' . $path);
        }
        $this->values = isset($this->data['values']) && is_array($this->data['values'])
            ? $this->data['values']
            : [];
    }

    public function authConfig($profile = null)
    {
        $auth = isset($this->data['auth']) ? $this->data['auth'] : [];
        if ($profile !== null && $profile !== '' && $profile !== true) {
            $profiles = isset($this->data['authProfiles']) && is_array($this->data['authProfiles'])
                ? $this->data['authProfiles']
                : [];
            if (!isset($profiles[$profile]) || !is_array($profiles[$profile])) {
                throw new \RuntimeException("Unknown auth profile: {$profile}");
            }
            $auth = array_merge($auth, $profiles[$profile]);
        }

        return [
            'clientId' => $this->resolveEnvValue(
                getenv('API_TEST_CLIENT_ID') ?: (isset($auth['clientId']) ? $auth['clientId'] : 'projects4me')
            ),
            'clientSecret' => $this->resolveEnvValue(
                getenv('API_TEST_CLIENT_SECRET') ?: (isset($auth['clientSecret']) ? $auth['clientSecret'] : '')
            ),
            'email' => $this->resolveEnvValue(
                getenv('API_TEST_EMAIL') ?: (isset($auth['email']) ? $auth['email'] : '')
            ),
            'password' => $this->resolveEnvValue(
                getenv('API_TEST_PASSWORD') ?: (isset($auth['password']) ? $auth['password'] : '')
            ),
            'tokenPath' => isset($auth['tokenPath']) ? $auth['tokenPath'] : '/api/v1/token',
        ];
    }

    public function setValue($key, $value)
    {
        $this->values[$key] = $value;
    }

    public function getValue($key, $default = null)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function resolve($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->resolve($v);
            }
            return $out;
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = $this->resolveEnvValue($value);

        return preg_replace_callback(
            '/\{\s*([a-zA-Z0-9_.\(\)]+)\s*\}|\$\{\s*([a-zA-Z0-9_.\(\)]+)\s*\}/',
            function ($matches) {
                $key = !empty($matches[1]) ? $matches[1] : $matches[2];
                if (array_key_exists($key, $this->values)) {
                    return (string) $this->values[$key];
                }
                // Keep unresolved placeholders visible for mismatch reporting
                return '{' . $key . '}';
            },
            $value
        );
    }

    private function resolveEnvValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        if (preg_match('/^\$\{ENV:([A-Z0-9_]+)\}$/', $value, $m)) {
            $env = getenv($m[1]);
            return $env === false ? '' : $env;
        }
        return $value;
    }
}
