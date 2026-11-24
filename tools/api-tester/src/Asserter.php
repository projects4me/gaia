<?php

namespace ApiTester;

class Asserter
{
    /**
     * The result ids of each passed case so far, by case id, for idsSameAs.
     *
     * @var array
     */
    private $caseIds = [];

    /**
     * Keep a passed case's result ids, so a later case can compare its results with them (idsSameAs).
     *
     * @param string $caseId
     * @param array  $response
     */
    public function rememberCase($caseId, array $response)
    {
        $this->caseIds[(string) $caseId] = $this->collectionIds(isset($response['json']) ? $response['json'] : null);
    }

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

        if (!empty($expects['jsonEqual']) && is_array($expects['jsonEqual'])) {
            foreach ($expects['jsonEqual'] as $key => $expected) {
                $actual = $this->jsonValue($json, $key);
                if (!is_scalar($actual) || (string) $actual !== (string) $expected) {
                    $failures[] = "jsonEqual: {$key} expected '{$expected}', got '" .
                        (is_scalar($actual) ? $actual : json_encode($actual)) . "'";
                }
            }
        }

        if (!empty($expects['jsonPathAbsent']) && is_array($expects['jsonPathAbsent'])) {
            foreach ($expects['jsonPathAbsent'] as $path) {
                if ($this->valueAtPath($json, $path, $actual)) {
                    $failures[] = "jsonPathAbsent: unexpectedly found {$path}";
                }
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

        if (!empty($expects['includedTypesPresent']) && is_array($expects['includedTypesPresent'])) {
            $includedTypes = [];
            if (isset($json['included']) && is_array($json['included'])) {
                foreach ($json['included'] as $row) {
                    if (isset($row['type'])) {
                        $includedTypes[] = $row['type'];
                    }
                }
            }
            foreach ($expects['includedTypesPresent'] as $type) {
                if (!in_array($type, $includedTypes, true)) {
                    $failures[] = "includedTypesPresent: missing type {$type}";
                }
            }
        }

        if (!empty($expects['resourceNamesAbsent']) && is_array($expects['resourceNamesAbsent'])) {
            $names = $this->resourceNames($json);
            foreach ($expects['resourceNamesAbsent'] as $name) {
                if (in_array($name, $names, true)) {
                    $failures[] = "resourceNamesAbsent: unexpectedly found resourceName {$name}";
                }
            }
        }

        if (!empty($expects['resourceNamesPresent']) && is_array($expects['resourceNamesPresent'])) {
            $names = $this->resourceNames($json);
            foreach ($expects['resourceNamesPresent'] as $name) {
                if (!in_array($name, $names, true)) {
                    $failures[] = "resourceNamesPresent: missing resourceName {$name}";
                }
            }
        }

        if (!empty($expects['attributesPresent']) && is_array($expects['attributesPresent'])) {
            foreach ($this->attributeMaps($json) as $index => $attrs) {
                foreach ($expects['attributesPresent'] as $attr) {
                    if (!array_key_exists($attr, $attrs)) {
                        $failures[] = "attributesPresent: missing attributes.{$attr}" .
                            ($index !== null ? " (item {$index})" : '');
                    }
                }
            }
        }

        if (!empty($expects['attributesAbsent']) && is_array($expects['attributesAbsent'])) {
            foreach ($this->attributeMaps($json) as $index => $attrs) {
                foreach ($expects['attributesAbsent'] as $attr) {
                    if (array_key_exists($attr, $attrs)) {
                        $failures[] = "attributesAbsent: unexpectedly found attributes.{$attr}" .
                            ($index !== null ? " (item {$index})" : '');
                    }
                }
            }
        }

        $checkIncludes = !empty($expects['idsIncludes']) && is_array($expects['idsIncludes']);
        $checkExcludes = !empty($expects['idsExcludes']) && is_array($expects['idsExcludes']);
        if ($checkIncludes || $checkExcludes) {
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
            foreach ($checkIncludes ? $expects['idsIncludes'] : [] as $expectedId) {
                if (!in_array(trim((string) $expectedId), $ids, true)) {
                    $failures[] = "idsIncludes: missing id {$expectedId}";
                }
            }
            foreach ($checkExcludes ? $expects['idsExcludes'] : [] as $unexpectedId) {
                if (in_array(trim((string) $unexpectedId), $ids, true)) {
                    $failures[] = "idsExcludes: unexpectedly found id {$unexpectedId}";
                }
            }
        }

        // Ordered result lists (search): the ids in order, nothing else; [] means no results
        if (array_key_exists('idsEqual', $expects) && is_array($expects['idsEqual'])) {
            $ids = $this->collectionIds($json);
            $expected = array_map('strval', array_values($expects['idsEqual']));
            if ($ids !== $expected) {
                $failures[] = 'idsEqual: expected [' . implode(', ', $expected) . '], got [' . implode(', ', $ids) . ']';
            }
        }

        // Every id ranks at or above `rank` (1 = first)
        if (!empty($expects['idsWithin']) && is_array($expects['idsWithin'])) {
            $rank = isset($expects['idsWithin']['rank']) ? (int) $expects['idsWithin']['rank'] : 0;
            $within = isset($expects['idsWithin']['ids']) ? (array) $expects['idsWithin']['ids'] : [];
            if ($rank < 1 || $within === []) {
                $failures[] = 'idsWithin: needs a rank of 1 or more and at least one id';
            }
            $top = array_slice($this->collectionIds($json), 0, max($rank, 0));
            foreach ($within as $expectedId) {
                if (!in_array((string) $expectedId, $top, true)) {
                    $failures[] = "idsWithin: {$expectedId} is not in the top {$rank}";
                }
            }
        }

        // Every listed id that is returned ranks above every `before` id that is returned
        if (!empty($expects['idsBefore']) && is_array($expects['idsBefore'])) {
            $places = array_flip($this->collectionIds($json));
            $ids = isset($expects['idsBefore']['ids']) ? (array) $expects['idsBefore']['ids'] : [];
            $later = isset($expects['idsBefore']['before']) ? (array) $expects['idsBefore']['before'] : [];
            foreach ($ids as $id) {
                if (!isset($places[(string) $id])) {
                    $failures[] = "idsBefore: missing id {$id}";
                    continue;
                }
                foreach ($later as $other) {
                    if (isset($places[(string) $other]) && $places[(string) $other] < $places[(string) $id]) {
                        $failures[] = "idsBefore: {$other} ranks above {$id}";
                    }
                }
            }
        }

        // The ids an earlier passed case returned (rememberCase): the same list, or its first `top`
        if (!empty($expects['idsSameAs']) && is_array($expects['idsSameAs'])) {
            $same = $expects['idsSameAs'];
            $case = isset($same['case']) ? (string) $same['case'] : '';
            if (!isset($this->caseIds[$case]) || $this->caseIds[$case] === []) {
                $failures[] = "idsSameAs: case {$case} has no results to compare with (not run, skipped, failed or empty)";
            } else {
                $ids = $this->collectionIds($json);
                $other = $this->caseIds[$case];
                if (isset($same['top'])) {
                    $ids = array_slice($ids, 0, (int) $same['top']);
                    $other = array_slice($other, 0, (int) $same['top']);
                }
                if (empty($same['ordered'])) {
                    sort($ids);
                    sort($other);
                }
                if ($ids !== $other) {
                    $failures[] = "idsSameAs: expected the results of {$case} [" . implode(', ', $other)
                        . '], got [' . implode(', ', $ids) . ']';
                }
            }
        }

        // Every attributes.score in [min, max]
        if (!empty($expects['scoresWithin']) && is_array($expects['scoresWithin'])) {
            list($min, $max) = array_values($expects['scoresWithin']) + [0, 0];
            foreach ($this->collectionScores($json) as list($id, $score)) {
                if (!is_numeric($score) || $score < $min || $score > $max) {
                    $failures[] = "scoresWithin: {$id} scores " . json_encode($score) . ", outside [{$min}, {$max}]";
                }
            }
        }

        // attributes.score never rises down the list
        if (!empty($expects['scoresFalling'])) {
            $previous = null;
            foreach ($this->collectionScores($json) as list($id, $score)) {
                if (!is_numeric($score)) {
                    $failures[] = "scoresFalling: {$id} has no numeric score";
                    continue;
                }
                if ($previous !== null && $score > $previous) {
                    $failures[] = "scoresFalling: {$id} scores {$score}, above the result before it ({$previous})";
                }
                $previous = $score;
            }
        }

        if (!empty($expects['attributesEqual']) && is_array($expects['attributesEqual'])) {
            foreach ($this->attributeMaps($json) as $index => $attrs) {
                foreach ($expects['attributesEqual'] as $attr => $expected) {
                    if (!array_key_exists($attr, $attrs)) {
                        $failures[] = "attributesEqual: missing attributes.{$attr}" .
                            ($index !== null ? " (item {$index})" : '');
                        continue;
                    }
                    $actual = $attrs[$attr];
                    if ((string) $actual !== (string) $expected) {
                        $failures[] = "attributesEqual: attributes.{$attr} expected '{$expected}', got '" .
                            (is_scalar($actual) ? $actual : json_encode($actual)) . "'" .
                            ($index !== null ? " (item {$index})" : '');
                    }
                }
            }
        }

        return $failures;
    }

    /**
     * Reads a top-level key, or a nested one by dotted path (e.g. meta.unreadCount).
     */
    private function jsonValue($json, $key)
    {
        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }
        $value = $json;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /**
     * Find the value at a dotted path (e.g. `data.attributes.translations.en-us`).
     *
     * @param  mixed  $json
     * @param  string $path
     * @param  mixed  $value Set to the value when found
     * @return bool Whether the path exists
     */
    private function valueAtPath($json, $path, &$value)
    {
        $value = $json;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                $value = null;
                return false;
            }
            $value = $value[$key];
        }

        return true;
    }

    /**
     * Collect attributes maps from a JSON:API resource or collection.
     *
     * For collections, only the first item is checked (deterministic seed order).
     *
     * @param  array|null $json
     * @return array list of attribute maps keyed by index (0 for resource/collection first item)
     */
    private function attributeMaps($json)
    {
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return [0 => []];
        }

        if (isset($json['data']['attributes']) && is_array($json['data']['attributes'])) {
            return [0 => $json['data']['attributes']];
        }

        // Collection: assert against first row when present
        foreach ($json['data'] as $index => $row) {
            if (isset($row['attributes']) && is_array($row['attributes'])) {
                return [$index => $row['attributes']];
            }
        }

        return [0 => []];
    }

    /**
     * The ids of a collection's data items, in order.
     *
     * @param  array|null $json
     * @return string[]
     */
    private function collectionIds($json)
    {
        $ids = [];
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data']) || isset($json['data']['id'])) {
            return $ids;
        }
        foreach ($json['data'] as $row) {
            if (is_array($row) && isset($row['id'])) {
                $ids[] = trim((string) $row['id']);
            }
        }

        return $ids;
    }

    /**
     * The attributes.score of a collection's data items, in order, as [id, score] pairs.
     *
     * @param  array|null $json
     * @return array[]
     */
    private function collectionScores($json)
    {
        $scores = [];
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data']) || isset($json['data']['id'])) {
            return $scores;
        }
        foreach ($json['data'] as $index => $row) {
            $id = is_array($row) && isset($row['id']) ? (string) $row['id'] : (string) $index;
            $scores[] = [$id, isset($row['attributes']['score']) ? $row['attributes']['score'] : null];
        }

        return $scores;
    }

    /**
     * Collect attributes.resourceName values from a JSON:API collection/resource.
     *
     * @param  array|null $json
     * @return array
     */
    private function resourceNames($json)
    {
        $names = [];
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return $names;
        }

        $rows = isset($json['data']['attributes']) ? [$json['data']] : $json['data'];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['attributes']['resourceName'])) {
                continue;
            }
            $names[] = (string) $row['attributes']['resourceName'];
        }

        return $names;
    }
}
