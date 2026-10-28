<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tools;

use Gaia\MVC\Models\Toolserver;

/**
 * Refreshes a tool server's catalog entry from the server itself: lists its
 * tools with MCP tools/list (McpToolLister), stores them (ToolCatalogSync)
 * and stamps schemaRefreshedAt, all in one transaction. A failed refresh
 * keeps the stored tools and records the reason in refreshError.
 *
 * The server owns the tool schemas; Gaia owns the catalog entry and grants.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolServerRefresher
{
    /**
     * @var McpToolLister
     */
    private $lister;

    /**
     * @var ToolCatalogSync
     */
    private $sync;

    /**
     * @param McpToolLister|null   $lister
     * @param ToolCatalogSync|null $sync
     */
    public function __construct(McpToolLister $lister = null, ToolCatalogSync $sync = null)
    {
        $this->lister = $lister ?: new McpToolLister();
        $this->sync = $sync ?: new ToolCatalogSync();
    }

    /**
     * The live server, with the given fields over it (to check a pending
     * update before it is saved).
     *
     * @param  string $serverId
     * @param  array  $overrides
     * @return Toolserver
     * @throws \Gaia\Exception\ResourceNotFound
     */
    public function findServer($serverId, array $overrides = [])
    {
        $server = Toolserver::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $serverId],
        ]);
        if (!$server) {
            throw new \Gaia\Exception\ResourceNotFound('Tool server not found.');
        }

        foreach (['kind', 'transport', 'endpoint'] as $field) {
            if (array_key_exists($field, $overrides)) {
                $server->$field = $overrides[$field];
            }
        }

        return $server;
    }

    /**
     * Reject a server Gaia cannot list: only MCP servers reached over HTTP.
     * Runtime built-ins and stdio servers are entered by hand.
     *
     * @param  Toolserver $server
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertRefreshable(Toolserver $server)
    {
        if ($server->kind !== 'mcp' || $server->transport !== 'http' || (string) $server->endpoint === '') {
            throw new \Gaia\Exception\Permission(
                'Only MCP servers with the http transport and an endpoint can be refreshed.'
            );
        }
    }

    /**
     * Reload the server's tools.
     *
     * @param  Toolserver $server
     * @return array ['added' => int, 'updated' => int, 'removed' => int]
     * @throws \Gaia\Exception\Permission When the server cannot be refreshed
     * @throws ToolRefreshException When listing or storing the tools fails
     */
    public function refresh(Toolserver $server)
    {
        $this->assertRefreshable($server);
        $lastRefreshedAt = $server->schemaRefreshedAt;

        try {
            $listed = $this->lister->listTools((string) $server->endpoint);

            return $this->transactional(function () use ($server, $listed) {
                $counts = $this->sync->sync((string) $server->id, $listed);
                $this->stamp($server, date('Y-m-d H:i:s'), null);
                return $counts;
            });
        } catch (ToolRefreshException $e) {
            try {
                $this->stamp($server, $lastRefreshedAt, $e->getMessage());
            } catch (\Throwable $ignored) {
                // Recording the error must not hide the error itself.
            }
            throw $e;
        }
    }

    /**
     * Record the refresh time and error on the server.
     *
     * @param  Toolserver  $server
     * @param  string|null $refreshedAt
     * @param  string|null $error
     * @return void
     * @throws ToolRefreshException
     */
    protected function stamp(Toolserver $server, $refreshedAt, $error)
    {
        $server->schemaRefreshedAt = $refreshedAt;
        $server->refreshError = $error;
        if (!$server->save()) {
            throw new ToolRefreshException('The tool server could not be updated.');
        }
    }

    /**
     * Run the work in one database transaction.
     *
     * @param  callable $work
     * @return mixed
     * @throws \Throwable
     */
    protected function transactional(callable $work)
    {
        $db = \Phalcon\Di::getDefault()->getShared('db');
        $db->begin();

        try {
            $result = $work();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            try {
                $db->rollback();
            } catch (\Throwable $ignored) {
                // No open transaction (or already rolled back).
            }
            throw $e;
        }
    }
}
