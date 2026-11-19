<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\ApiTester;

use ApiTester\ReportComparer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for comparing two recorded api-tester reports (agaia-4685).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ReportComparerTest extends TestCase
{
    /**
     * A recorded case result.
     *
     * @param string $id
     * @param mixed $body decoded JSON body, or a string for a text body
     * @param array $overrides response fields to replace
     * @return array
     */
    private function recordedCase($id, $body, array $overrides = [])
    {
        return [
            'id' => $id,
            'status' => 'passed',
            'endpoint' => 'GET /api/v1/issue/{issueId}',
            'response' => array_merge([
                'status' => 200,
                'contentType' => 'application/json; charset=UTF-8',
                'bodyFormat' => is_string($body) ? 'text' : 'json',
                'body' => $body,
            ], $overrides),
        ];
    }

    /**
     * Encode and decode as a report file, so objects stay objects.
     *
     * @param array $results
     * @param string $runId
     * @return \stdClass
     */
    private function report(array $results, $runId = 'aaaa1111')
    {
        return json_decode(json_encode(['runId' => $runId, 'results' => $results]));
    }

    /**
     * @param array $a results of report a
     * @param array $b results of report b
     * @return array differences
     */
    private function diff(array $a, array $b)
    {
        return (new ReportComparer())
            ->compare($this->report($a, 'aaaa1111'), $this->report($b, 'bbbb2222'))
            ->differences();
    }

    public function testRunsDifferingOnlyInRuntimeValuesCompareClean()
    {
        $a = $this->recordedCase('issue read', ['data' => [
            'id' => '0f8fad5b-d9cb-469f-a165-70867728950e',
            'subject' => 'Tag tag-aaaa1111',
            'dateCreated' => '2026-11-19 04:44:10',
            'link' => '/api/v1/issue/0f8fad5b-d9cb-469f-a165-70867728950e',
            'access_token' => 'abc',
            'related' => ['0f8fad5b-d9cb-469f-a165-70867728950e' => ['name' => 'x']],
        ]]);
        $b = $this->recordedCase('issue read', ['data' => [
            'id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'subject' => 'Tag tag-bbbb2222',
            'dateCreated' => '2026-11-20T09:01:02+00:00',
            'link' => '/api/v1/issue/7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'access_token' => 'xyz',
            'related' => ['7c9e6679-7425-40de-944b-e07fc1f90ae7' => ['name' => 'x']],
        ]]);

        $comparison = (new ReportComparer())->compare($this->report([$a], 'aaaa1111'), $this->report([$b], 'bbbb2222'));

        $this->assertTrue($comparison->isClean(), json_encode($comparison->differences()));
        $this->assertSame(1, $comparison->casesCompared());
    }

    public function testDurationMsDifferencesAreIgnored()
    {
        $a = $this->recordedCase('bulk', ['responses' => [['status' => 200, 'durationMs' => 12]]]);
        $b = $this->recordedCase('bulk', ['responses' => [['status' => 200, 'durationMs' => 345]]]);

        $comparison = (new ReportComparer())->compare($this->report([$a], 'aaaa1111'), $this->report([$b], 'bbbb2222'));

        $this->assertTrue($comparison->isClean(), json_encode($comparison->differences()));
    }

    public function testGaiaGeneratedIdsAreIgnoredButOtherDifferencesAreReported()
    {
        $a = $this->recordedCase('issue delete', ['data' => [
            'id' => '73094b556ae8-11b0-bd37-4da81643c41f',
            'attributes' => ['ownerId' => '72e3d4a86ae8-11b0-bd37-a3bd55a4171b', 'name' => 'same'],
            'links' => ['self' => '/api/v1/issue/73094b556ae8-11b0-bd37-4da81643c41f'],
        ]]);
        $b = $this->recordedCase('issue delete', ['data' => [
            'id' => '8a1b2c3d4e5f-22aa-33bb-0123456789ab',
            'attributes' => ['ownerId' => '9f8e7d6c5b4a-44cc-55dd-ba9876543210', 'name' => 'changed'],
            'links' => ['self' => '/api/v1/issues/8a1b2c3d4e5f-22aa-33bb-0123456789ab'],
        ]]);

        $differences = $this->diff([$a], [$b]);

        $paths = array_column($differences, 'path');
        sort($paths);
        $this->assertSame(['$.data.attributes.name', '$.data.links.self'], $paths);
    }

    public function testEmptyBodyInOneReportIsReported()
    {
        $differences = $this->diff(
            [$this->recordedCase('bulk', ['results' => []])],
            [$this->recordedCase('bulk', '', ['contentType' => null])]
        );

        $kinds = array_column($differences, 'kind');
        $this->assertContains(ReportComparer::KIND_EMPTY_BODY, $kinds);
        $this->assertContains(ReportComparer::KIND_CONTENT_TYPE, $kinds);
        $emptyBody = $differences[array_search(ReportComparer::KIND_EMPTY_BODY, $kinds)];
        $this->assertSame('non-empty', $emptyBody['a']);
        $this->assertSame('empty', $emptyBody['b']);
        $this->assertSame('GET /api/v1/issue/{issueId}', $emptyBody['endpoint']);
    }

    public function testChangedValueTypeIsReported()
    {
        $differences = $this->diff(
            [$this->recordedCase('count', ['data' => ['count' => 1]])],
            [$this->recordedCase('count', ['data' => ['count' => '1']])]
        );

        $this->assertCount(1, $differences);
        $this->assertSame(ReportComparer::KIND_TYPE, $differences[0]['kind']);
        $this->assertSame('$.data.count', $differences[0]['path']);
        $this->assertSame('integer 1', $differences[0]['a']);
        $this->assertSame('string "1"', $differences[0]['b']);
    }

    public function testEmptyObjectAndEmptyListAreDifferentTypes()
    {
        $differences = $this->diff(
            [$this->recordedCase('meta', ['meta' => new \stdClass()])],
            [$this->recordedCase('meta', ['meta' => []])]
        );

        $this->assertSame([ReportComparer::KIND_TYPE], array_column($differences, 'kind'));
    }

    public function testStatusKeysListLengthsAndValuesAreReported()
    {
        $differences = $this->diff(
            [$this->recordedCase('list', ['data' => [['name' => 'a'], ['name' => 'b']], 'old' => true])],
            [$this->recordedCase('list', ['data' => [['name' => 'z']], 'new' => true], ['status' => 201])]
        );

        $byKind = [];
        foreach ($differences as $difference) {
            $byKind[$difference['kind']][] = $difference['path'];
        }
        $this->assertSame([''], $byKind[ReportComparer::KIND_STATUS]);
        $this->assertSame(['$.data'], $byKind[ReportComparer::KIND_LENGTH]);
        $this->assertSame(['$.data[0].name'], $byKind[ReportComparer::KIND_VALUE]);
        $this->assertSame(['$.old', '$.new'], $byKind[ReportComparer::KIND_KEY]);
    }

    public function testIgnoredKeysStillCompareTypes()
    {
        $differences = $this->diff(
            [$this->recordedCase('token', ['access_token' => 'abc'])],
            [$this->recordedCase('token', ['access_token' => null])]
        );

        $this->assertSame([ReportComparer::KIND_TYPE], array_column($differences, 'kind'));
    }

    public function testJsonVersusTextBodyIsReported()
    {
        $differences = $this->diff(
            [$this->recordedCase('page', ['ok' => true])],
            [$this->recordedCase('page', '<html>error</html>')]
        );

        $this->assertSame([ReportComparer::KIND_FORMAT], array_column($differences, 'kind'));
    }

    public function testCaseLevelDifferences()
    {
        $notRecorded = ['id' => 'plain', 'status' => 'passed', 'response' => ['status' => 200, 'bodyPreview' => '{}']];
        $differences = $this->diff(
            [$this->recordedCase('only in a', []), $this->recordedCase('plain', []), $this->recordedCase('flips', [])],
            [$notRecorded, array_merge($this->recordedCase('flips', []), ['status' => 'failed'])]
        );

        $kinds = array_column($differences, 'kind', 'id');
        $this->assertSame(ReportComparer::KIND_MISSING_CASE, $kinds['only in a']);
        $this->assertSame(ReportComparer::KIND_NOT_RECORDED, $kinds['plain']);
        $this->assertSame(ReportComparer::KIND_OUTCOME, $kinds['flips']);
    }

    public function testGroupsByEndpointAndKind()
    {
        $comparison = (new ReportComparer())->compare(
            $this->report([$this->recordedCase('one', ['n' => 1]), $this->recordedCase('two', ['n' => 1])]),
            $this->report([$this->recordedCase('one', ['n' => 2]), $this->recordedCase('two', ['n' => '1'])])
        );

        $this->assertSame(['GET /api/v1/issue/{issueId}'], array_keys($comparison->byEndpoint()));
        $this->assertSame([ReportComparer::KIND_VALUE, ReportComparer::KIND_TYPE], array_keys($comparison->byKind()));
    }
}
