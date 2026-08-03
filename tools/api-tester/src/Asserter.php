<?php

namespace ApiTester;

class Asserter
{
    public function assert(array $expects, array $response)
    {
        $failures = [];

        if (isset($expects['status'])) {
            $expected = $expects['status'];
            $actual = $response['status'];
            $ok = is_array($expected) ? in_array($actual, $expected, true) : ((int) $expected === (int) $actual);
            if (!$ok) {
                $failures[] = sprintf(
                    'status: expected %s, got %s',
                    is_array($expected) ? '[' . implode(',', $expected) . ']' : $expected,
                    $actual
                );
            }
        }

        $shape = isset($expects['shape']) ? $expects['shape'] : null;
        $json = $response['json'];

        if ($shape === 'json' || $shape === 'jsonapi.collection' || $shape === 'jsonapi.resource' || $shape === 'oauth.token' || $shape === 'error') {
            if ($json === null) {
                $failures[] = 'json: response body is not valid JSON object/array';
                return $failures;
            }
        }

        if ($shape === 'oauth.token') {
            foreach (['access_token', 'token_type'] as $key) {
                if (!isset($json[$key]) || $json[$key] === '') {
                    $failures[] = "oauth.token: missing '{$key}'";
                }
            }
        }

        if ($shape === 'jsonapi.collection') {
            if (!isset($json['data']) || !is_array($json['data'])) {
                $failures[] = 'jsonapi.collection: missing array data';
            }
            if (!isset($json['meta']['links']['self'])) {
                $failures[] = 'jsonapi.collection: missing meta.links.self';
            }
        }

        if ($shape === 'jsonapi.resource') {
            if (!isset($json['data']) || !is_array($json['data'])) {
                $failures[] = 'jsonapi.resource: missing data object';
            } else {
                foreach (['id', 'type'] as $key) {
                    if (!isset($json['data'][$key])) {
                        $failures[] = "jsonapi.resource: missing data.{$key}";
                    }
                }
            }
            if (!isset($json['meta']['links']['self'])) {
                $failures[] = 'jsonapi.resource: missing meta.links.self';
            }
        }

        if ($shape === 'error') {
            $hasError = isset($json['error'])
                || isset($json['error_description'])
                || isset($json['status'])
                || isset($json['messages']);
            if (!$hasError) {
                $failures[] = 'error: expected error payload shape';
            }
        }

        if (array_key_exists('errorEqual', $expects)) {
            $actual = isset($json['error']) ? $json['error'] : null;
            if ((string) $actual !== (string) $expects['errorEqual']) {
                $failures[] = "errorEqual: expected '{$expects['errorEqual']}', got '" .
                    (is_scalar($actual) ? $actual : json_encode($actual)) . "'";
            }
        }

        if (!empty($expects['includedTypesAbsent']) && is_array($expects['includedTypesAbsent'])) {
            $includedTypes = [];
            if (isset($json['included']) && is_array($json['included'])) {
                foreach ($json['included'] as $row) {
                    if (isset($row['type'])) {
                        $includedTypes[] = $row['type'];
                    }
                }
            }
            foreach ($expects['includedTypesAbsent'] as $type) {
                if (in_array($type, $includedTypes, true)) {
                    $failures[] = "includedTypesAbsent: unexpectedly found type {$type}";
                }
            }
        }

        if (!empty($expects['attributesPresent']) && is_array($expects['attributesPresent'])) {
            $attrs = isset($json['data']['attributes']) ? $json['data']['attributes'] : [];
            foreach ($expects['attributesPresent'] as $attr) {
                if (!array_key_exists($attr, $attrs)) {
                    $failures[] = "attributesPresent: missing attributes.{$attr}";
                }
            }
        }

        if (!empty($expects['idsIncludes']) && is_array($expects['idsIncludes'])) {
            $ids = [];
            if (isset($json['data']) && is_array($json['data'])) {
                if (isset($json['data']['id'])) {
                    $ids[] = trim((string) $json['data']['id']);
                } else {
                    foreach ($json['data'] as $row) {
                        if (isset($row['id'])) {
                            $ids[] = trim((string) $row['id']);
                        }
                    }
                }
            }
            foreach ($expects['idsIncludes'] as $expectedId) {
                if (!in_array(trim((string) $expectedId), $ids, true)) {
                    $failures[] = "idsIncludes: missing id {$expectedId}";
                }
            }
        }

        if (!empty($expects['attributesEqual']) && is_array($expects['attributesEqual'])) {
            $attrs = isset($json['data']['attributes']) ? $json['data']['attributes'] : [];
            foreach ($expects['attributesEqual'] as $attr => $expected) {
                if (!array_key_exists($attr, $attrs)) {
                    $failures[] = "attributesEqual: missing attributes.{$attr}";
                    continue;
                }
                $actual = $attrs[$attr];
                if ((string) $actual !== (string) $expected) {
                    $failures[] = "attributesEqual: attributes.{$attr} expected '{$expected}', got '" .
                        (is_scalar($actual) ? $actual : json_encode($actual)) . "'";
                }
            }
        }

        return $failures;
    }
}
