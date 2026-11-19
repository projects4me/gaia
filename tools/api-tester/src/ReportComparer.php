<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace ApiTester;

/**
 * Compares two api-tester reports written with --record-bodies and lists how
 * the responses differ, case by case (cases are matched by id).
 *
 * Reports must be decoded with json_decode($json) (objects, not associative
 * arrays) so that `{}` and `[]` stay distinct.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package ApiTester
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ReportComparer
{
    /** The case is in one report only. */
    const KIND_MISSING_CASE = 'missing case';
    /** The case passed in one report and failed or was skipped in the other. */
    const KIND_OUTCOME = 'outcome';
    /** One report has no recorded body for the case (run without --record-bodies, or skipped). */
    const KIND_NOT_RECORDED = 'not recorded';
    const KIND_STATUS = 'status';
    const KIND_CONTENT_TYPE = 'content type';
    /** One body is empty and the other is not. */
    const KIND_EMPTY_BODY = 'empty body';
    /** One body is JSON and the other is text. */
    const KIND_FORMAT = 'body format';
    /** An object key is in one body only. */
    const KIND_KEY = 'key';
    /** A JSON list has a different length. */
    const KIND_LENGTH = 'list length';
    /** The same key holds a different JSON type (e.g. 1 vs "1", {} vs []). */
    const KIND_TYPE = 'value type';
    const KIND_VALUE = 'value';

    private $ignore;

    /** @var array runId masks for report a and report b */
    private $runIds = ['a' => null, 'b' => null];

    /** @var array differences collected for the current comparison */
    private $differences = [];

    /**
     * @param IgnoreList|null $ignore null for the default ignore list
     */
    public function __construct(?IgnoreList $ignore = null)
    {
        $this->ignore = $ignore === null ? new IgnoreList() : $ignore;
    }

    /**
     * @param \stdClass $a decoded report
     * @param \stdClass $b decoded report
     * @return Comparison
     */
    public function compare(\stdClass $a, \stdClass $b)
    {
        $this->differences = [];
        $this->runIds = [
            'a' => isset($a->runId) ? (string) $a->runId : null,
            'b' => isset($b->runId) ? (string) $b->runId : null,
        ];

        $casesA = $this->indexCases($a);
        $casesB = $this->indexCases($b);
        $compared = 0;

        foreach ($casesA as $id => $caseA) {
            if (!isset($casesB[$id])) {
                $this->add($id, $caseA, self::KIND_MISSING_CASE, '', 'present', 'missing');
                continue;
            }
            $compared++;
            $this->compareCase($id, $caseA, $casesB[$id]);
        }
        foreach ($casesB as $id => $caseB) {
            if (!isset($casesA[$id])) {
                $this->add($id, $caseB, self::KIND_MISSING_CASE, '', 'missing', 'present');
            }
        }

        return new Comparison($this->differences, $compared);
    }

    /**
     * @param \stdClass $report
     * @return array case id => result; a repeated id gets a `#n` suffix
     */
    private function indexCases(\stdClass $report)
    {
        $cases = [];
        $results = isset($report->results) && is_array($report->results) ? $report->results : [];
        foreach ($results as $result) {
            $id = isset($result->id) ? (string) $result->id : '(no id)';
            $key = $id;
            for ($n = 2; isset($cases[$key]); $n++) {
                $key = $id . ' #' . $n;
            }
            $cases[$key] = $result;
        }
        return $cases;
    }

    /**
     * @param string $id
     * @param \stdClass $a
     * @param \stdClass $b
     */
    private function compareCase($id, \stdClass $a, \stdClass $b)
    {
        $outcomeA = isset($a->status) ? $a->status : null;
        $outcomeB = isset($b->status) ? $b->status : null;
        if ($outcomeA !== $outcomeB) {
            $this->add($id, $a, self::KIND_OUTCOME, '', $outcomeA, $outcomeB);
        }

        $recordedA = isset($a->response) && property_exists($a->response, 'bodyFormat');
        $recordedB = isset($b->response) && property_exists($b->response, 'bodyFormat');
        if (!$recordedA && !$recordedB) {
            return;
        }
        if (!$recordedA || !$recordedB) {
            $this->add($id, $a, self::KIND_NOT_RECORDED, '', $recordedA ? 'recorded' : 'not recorded', $recordedB ? 'recorded' : 'not recorded');
            return;
        }

        $responseA = $a->response;
        $responseB = $b->response;
        if ($responseA->status !== $responseB->status) {
            $this->add($id, $a, self::KIND_STATUS, '', $responseA->status, $responseB->status);
        }

        $typeA = $this->normaliseContentType($responseA->contentType);
        $typeB = $this->normaliseContentType($responseB->contentType);
        if ($typeA !== $typeB) {
            $this->add($id, $a, self::KIND_CONTENT_TYPE, '', $responseA->contentType, $responseB->contentType);
        }

        $emptyA = $this->isEmptyBody($responseA);
        $emptyB = $this->isEmptyBody($responseB);
        if ($emptyA || $emptyB) {
            if ($emptyA !== $emptyB) {
                $this->add($id, $a, self::KIND_EMPTY_BODY, '$', $emptyA ? 'empty' : 'non-empty', $emptyB ? 'empty' : 'non-empty');
            }
            return;
        }

        if ($responseA->bodyFormat !== $responseB->bodyFormat) {
            $this->add($id, $a, self::KIND_FORMAT, '$', $responseA->bodyFormat, $responseB->bodyFormat);
            return;
        }

        $this->compareValue($id, $a, '$', $responseA->body, $responseB->body);
    }

    /**
     * @param string $id
     * @param \stdClass $case the case in report a (for its endpoint)
     * @param string $path
     * @param mixed $a
     * @param mixed $b
     * @param bool $ignoreValue compare types only (the key is on the ignore list)
     */
    private function compareValue($id, \stdClass $case, $path, $a, $b, $ignoreValue = false)
    {
        $typeA = $this->typeOf($a);
        $typeB = $this->typeOf($b);
        if ($typeA !== $typeB) {
            $this->add($id, $case, self::KIND_TYPE, $path, $typeA . ' ' . $this->preview($a), $typeB . ' ' . $this->preview($b));
            return;
        }

        if ($typeA === 'object') {
            $this->compareObjects($id, $case, $path, $a, $b);
            return;
        }

        if ($typeA === 'list') {
            if (count($a) !== count($b)) {
                $this->add($id, $case, self::KIND_LENGTH, $path, count($a), count($b));
            }
            $common = min(count($a), count($b));
            for ($i = 0; $i < $common; $i++) {
                $this->compareValue($id, $case, $path . '[' . $i . ']', $a[$i], $b[$i], $ignoreValue);
            }
            return;
        }

        if ($ignoreValue) {
            return;
        }

        $normalA = $typeA === 'string' ? $this->maskString($a, 'a') : $a;
        $normalB = $typeB === 'string' ? $this->maskString($b, 'b') : $b;
        if ($normalA !== $normalB) {
            $this->add($id, $case, self::KIND_VALUE, $path, $a, $b);
        }
    }

    /**
     * @param string $id
     * @param \stdClass $case
     * @param string $path
     * @param \stdClass $a
     * @param \stdClass $b
     */
    private function compareObjects($id, \stdClass $case, $path, \stdClass $a, \stdClass $b)
    {
        $keysA = $this->maskedKeys($a, 'a');
        $keysB = $this->maskedKeys($b, 'b');

        foreach ($keysA as $masked => $key) {
            $childPath = $path . '.' . $masked;
            if (!isset($keysB[$masked])) {
                $this->add($id, $case, self::KIND_KEY, $childPath, 'present', 'missing');
                continue;
            }
            $this->compareValue(
                $id,
                $case,
                $childPath,
                $a->{$key},
                $b->{$keysB[$masked]},
                $this->ignore->ignoresKey($key)
            );
        }
        foreach ($keysB as $masked => $key) {
            if (!isset($keysA[$masked])) {
                $this->add($id, $case, self::KIND_KEY, $path . '.' . $masked, 'missing', 'present');
            }
        }
    }

    /**
     * Object keys with runtime values masked (objects keyed by GUID), in order;
     * equal masked keys are numbered so they still pair up one to one.
     *
     * @param \stdClass $object
     * @param string $side a|b
     * @return array masked key => original key
     */
    private function maskedKeys(\stdClass $object, $side)
    {
        $keys = [];
        foreach (array_keys(get_object_vars($object)) as $key) {
            $masked = $this->maskString((string) $key, $side);
            $unique = $masked;
            for ($n = 2; isset($keys[$unique]); $n++) {
                $unique = $masked . '#' . $n;
            }
            $keys[$unique] = $key;
        }
        return $keys;
    }

    /**
     * @param string $value
     * @param string $side a|b
     * @return string
     */
    private function maskString($value, $side)
    {
        $runId = $this->runIds[$side];
        if ($runId !== null && $runId !== '') {
            $value = str_replace($runId, '{runId}', $value);
        }
        return $this->ignore->mask($value);
    }

    /**
     * @param mixed $value
     * @return string null, boolean, integer, float, string, list or object
     */
    private function typeOf($value)
    {
        if ($value instanceof \stdClass) {
            return 'object';
        }
        if (is_array($value)) {
            return 'list';
        }
        if (is_float($value)) {
            return 'float';
        }
        return $value === null ? 'null' : gettype($value);
    }

    /**
     * @param \stdClass $response
     * @return bool
     */
    private function isEmptyBody(\stdClass $response)
    {
        return $response->bodyFormat === 'text' && trim((string) $response->body) === '';
    }

    /**
     * @param string|null $contentType
     * @return string
     */
    private function normaliseContentType($contentType)
    {
        return strtolower(str_replace(' ', '', (string) $contentType));
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function preview($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES);
        return strlen($json) > 60 ? substr($json, 0, 60) . '...' : $json;
    }

    /**
     * @param string $id
     * @param \stdClass $case
     * @param string $kind
     * @param string $path
     * @param mixed $a
     * @param mixed $b
     */
    private function add($id, \stdClass $case, $kind, $path, $a, $b)
    {
        $endpoint = isset($case->endpoint) ? (string) $case->endpoint : null;
        if ($endpoint === null && isset($case->request->method, $case->request->url)) {
            $endpoint = $case->request->method . ' ' . parse_url($case->request->url, PHP_URL_PATH);
        }
        $this->differences[] = [
            'id' => $id,
            'endpoint' => $endpoint === null ? $id : $endpoint,
            'kind' => $kind,
            'path' => $path,
            'a' => $a,
            'b' => $b,
        ];
    }
}
