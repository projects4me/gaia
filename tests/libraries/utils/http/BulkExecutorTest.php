<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils\Http;

use Gaia\Core\MVC\Http\BulkExecutor;
use Gaia\Core\MVC\Http\BulkItem;
use Gaia\Core\MVC\Http\SubRequestRunner;
use Gaia\Core\MVC\Http\SubResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a bulk batch runs every call in order with the caller's token
 * and returns each call's own result, failures included.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BulkExecutorTest extends TestCase
{
    public function testEveryCallRunsInOrderAndKeepsItsOwnResult(): void
    {
        $runner = $this->createMock(SubRequestRunner::class);
        $runner->expects($this->exactly(3))
            ->method('run')
            ->withConsecutive(
                ['POST', '/api/v1/tag', ['data' => ['type' => 'tag']], 'Bearer abc'],
                ['GET', '/api/v1/issue/missing', null, 'Bearer abc'],
                ['GET', '/api/v1/project', null, 'Bearer abc']
            )
            ->willReturnOnConsecutiveCalls(
                new SubResult(201, ['data' => ['id' => 't1']], 12.4),
                new SubResult(404, ['error' => 'Not Found'], 3.6),
                new SubResult(403, ['error' => 'Access Denied'], 1.0)
            );

        $responses = (new BulkExecutor($runner))->run([
            new BulkItem('write', 'POST', '/api/v1/tag', ['data' => ['type' => 'tag']]),
            new BulkItem('missing', 'GET', '/api/v1/issue/missing', null),
            new BulkItem('denied', 'GET', '/api/v1/project', null),
        ], 'Bearer abc', 'user-1');

        $this->assertSame([
            ['id' => 'write', 'status' => 201, 'body' => ['data' => ['id' => 't1']], 'durationMs' => 12],
            ['id' => 'missing', 'status' => 404, 'body' => ['error' => 'Not Found'], 'durationMs' => 4],
            ['id' => 'denied', 'status' => 403, 'body' => ['error' => 'Access Denied'], 'durationMs' => 1],
        ], $responses);
    }

    public function testOneLogLinePerBatchWithoutBodies(): void
    {
        $logger = new class {
            /** @var string[] */
            public $lines = [];

            public function info(string $line): void
            {
                $this->lines[] = $line;
            }
        };

        $runner = $this->createMock(SubRequestRunner::class);
        $runner->method('run')->willReturnOnConsecutiveCalls(
            new SubResult(200, ['secret' => 'body-value'], 1.0),
            new SubResult(403, ['error' => 'Access Denied'], 1.0)
        );

        (new BulkExecutor($runner, $logger))->run([
            new BulkItem('a', 'GET', '/api/v1/issue', null),
            new BulkItem('b', 'GET', '/api/v1/project', null),
        ], 'Bearer abc', 'user-1');
        $lines = $logger->lines;

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('Bulk user=user-1 items=2 statuses=200,403 totalMs=', $lines[0]);
        $this->assertStringNotContainsString('body-value', $lines[0]);
        $this->assertStringNotContainsString('Bearer', $lines[0]);
    }
}
