<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Tools;

use Gaia\Libraries\Tools\McpToolLister;
use Gaia\Libraries\Tools\ToolCatalogSync;
use Gaia\Libraries\Tools\ToolRefreshException;
use Gaia\Libraries\Tools\ToolServerRefresher;
use Gaia\MVC\Models\Toolserver;
use PHPUnit\Framework\TestCase;

/**
 * Tests which servers can be refreshed, and that a refresh stamps the time
 * on success and the error (keeping the last refresh time) on failure.
 * The lister, the sync and the save are stubbed.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolServerRefresherTest extends TestCase
{
    /**
     * @dataProvider unrefreshableServers
     */
    public function testRejectsAServerGaiaCannotList($kind, $transport, $endpoint)
    {
        $refresher = $this->refresher($this->createMock(McpToolLister::class), $this->createMock(ToolCatalogSync::class));

        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('Only MCP servers with the http transport and an endpoint can be refreshed.');
        $refresher->refresh($this->server($kind, $transport, $endpoint));
    }

    public function unrefreshableServers()
    {
        return [
            'builtin' => ['builtin', null, null],
            'openapi' => ['openapi', 'http', 'http://api.test/openapi.json'],
            'stdio' => ['mcp', 'stdio', null],
            'no endpoint' => ['mcp', 'http', ''],
        ];
    }

    public function testStoresTheListedToolsAndStampsTheTime()
    {
        $listed = [['name' => 'echo', 'description' => null, 'inputSchema' => null]];
        $lister = $this->createMock(McpToolLister::class);
        $lister->expects($this->once())->method('listTools')->with('http://mcp.test/mcp')->willReturn($listed);
        $sync = $this->createMock(ToolCatalogSync::class);
        $sync->expects($this->once())->method('sync')->with('server-1', $listed)->willReturn(['added' => 1, 'updated' => 0, 'removed' => 0]);

        $refresher = $this->refresher($lister, $sync);
        $server = $this->server('mcp', 'http', 'http://mcp.test/mcp');
        $server->refreshError = 'old error';

        $this->assertSame(['added' => 1, 'updated' => 0, 'removed' => 0], $refresher->refresh($server));
        $this->assertNotNull($server->schemaRefreshedAt);
        $this->assertNull($server->refreshError);
        $this->assertSame(1, $refresher->stamps);
    }

    public function testAFailedListKeepsTheToolsAndRecordsTheError()
    {
        $lister = $this->createMock(McpToolLister::class);
        $lister->method('listTools')->willThrowException(new ToolRefreshException('The tool server answered initialize with HTTP 401.'));
        $sync = $this->createMock(ToolCatalogSync::class);
        $sync->expects($this->never())->method('sync');

        $refresher = $this->refresher($lister, $sync);
        $server = $this->server('mcp', 'http', 'http://mcp.test/mcp');
        $server->schemaRefreshedAt = '2026-10-01 10:00:00';

        try {
            $refresher->refresh($server);
            $this->fail('A failed list must throw.');
        } catch (ToolRefreshException $e) {
            $this->assertSame('The tool server answered initialize with HTTP 401.', $server->refreshError);
            $this->assertSame('2026-10-01 10:00:00', $server->schemaRefreshedAt);
        }
    }

    public function testAFailedErrorStampKeepsTheOriginalError()
    {
        $lister = $this->createMock(McpToolLister::class);
        $lister->method('listTools')->willThrowException(new ToolRefreshException('The tool server could not be reached.'));
        $sync = $this->createMock(ToolCatalogSync::class);

        $refresher = new class ($lister, $sync) extends ToolServerRefresher {
            protected function stamp(Toolserver $server, $refreshedAt, $error)
            {
                throw new ToolRefreshException('The tool server could not be updated.');
            }
        };

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server could not be reached.');
        $refresher->refresh($this->server('mcp', 'http', 'http://mcp.test/mcp'));
    }

    /**
     * @param  string      $kind
     * @param  string|null $transport
     * @param  string|null $endpoint
     * @return Toolserver
     */
    private function server($kind, $transport, $endpoint)
    {
        $server = new Toolserver();
        $server->id = 'server-1';
        $server->kind = $kind;
        $server->transport = $transport;
        $server->endpoint = $endpoint;
        $server->schemaRefreshedAt = null;
        $server->refreshError = null;

        return $server;
    }

    /**
     * A refresher that stamps in memory and runs the work without a database.
     *
     * @param  McpToolLister   $lister
     * @param  ToolCatalogSync $sync
     * @return ToolServerRefresher
     */
    private function refresher($lister, $sync)
    {
        return new class ($lister, $sync) extends ToolServerRefresher {
            public $stamps = 0;

            protected function stamp(Toolserver $server, $refreshedAt, $error)
            {
                $this->stamps++;
                $server->schemaRefreshedAt = $refreshedAt;
                $server->refreshError = $error;
            }

            protected function transactional(callable $work)
            {
                return $work();
            }
        };
    }
}
