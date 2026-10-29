<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tools;

use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Agenttool;
use Gaia\MVC\Models\Tool;
use Gaia\MVC\Models\Toolserver;

/**
 * The rules a tool catalog write must follow: the allowed kinds, locations
 * and transports of a tool server, JSON in spec and inputSchema, and a grant
 * that names an existing agent and exactly one existing server or tool.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolCatalogRules
{
    /**
     * Kinds of tool server.
     */
    public const KINDS = ['mcp', 'openapi', 'builtin'];

    /**
     * Where a tool server runs.
     */
    public const LOCATIONS = ['runtime', 'ares', 'external'];

    /**
     * How a tool server is reached.
     */
    public const TRANSPORTS = ['stdio', 'http', 'sse'];

    /**
     * Check the tool server fields present in the payload.
     *
     * @param  array $attributes
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertServer(array $attributes)
    {
        $this->assertOneOf($attributes, 'kind', self::KINDS, false);
        $this->assertOneOf($attributes, 'location', self::LOCATIONS, false);
        $this->assertOneOf($attributes, 'transport', self::TRANSPORTS, true);
        $this->assertJsonObject($attributes, 'spec');
        $this->assertHttpUrl($attributes, 'endpoint');
    }

    /**
     * Check the tool fields present in the payload.
     *
     * @param  array $attributes
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertTool(array $attributes)
    {
        $this->assertJsonObject($attributes, 'inputSchema');

        if (!$this->isBlank($attributes, 'toolServerId') && !$this->exists(Toolserver::class, $attributes['toolServerId'])) {
            $this->reject('A tool can only belong to an existing tool server.');
        }
    }

    /**
     * Check a grant as it will be stored: the payload over the stored grant
     * when one is updated.
     *
     * @param  array       $attributes
     * @param  string|null $grantId    The grant being updated, null on create
     * @return array The grant as it will be stored
     * @throws \Gaia\Exception\Permission
     */
    public function assertGrant(array $attributes, $grantId = null)
    {
        $grant = array_merge($this->storedGrant($grantId), $attributes);

        if ($this->isBlank($grant, 'agentId') || !$this->exists(Agent::class, $grant['agentId'])) {
            $this->reject('A tool grant needs an existing agent.');
        }

        $hasServer = !$this->isBlank($grant, 'toolServerId');
        $hasTool = !$this->isBlank($grant, 'toolId');
        if ($hasServer === $hasTool) {
            $this->reject('A tool grant names either a tool server or a tool, not both.');
        }

        if ($hasServer && !$this->exists(Toolserver::class, $grant['toolServerId'])) {
            $this->reject('A tool grant needs an existing tool server.');
        }
        if ($hasTool && !$this->exists(Tool::class, $grant['toolId'])) {
            $this->reject('A tool grant needs an existing tool.');
        }

        return $grant;
    }

    /**
     * The stored fields of the grant being updated.
     *
     * @param  string|null $grantId
     * @return array
     */
    protected function storedGrant($grantId)
    {
        if ($grantId === null || (string) $grantId === '') {
            return [];
        }

        $grant = Agenttool::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $grantId],
        ]);

        return $grant ? [
            'agentId' => $grant->agentId,
            'toolServerId' => $grant->toolServerId,
            'toolId' => $grant->toolId,
        ] : [];
    }

    /**
     * Whether a live (not deleted) row of the model has this id.
     *
     * @param  string $modelClass
     * @param  mixed  $id
     * @return bool
     */
    protected function exists($modelClass, $id)
    {
        return (bool) $modelClass::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $id],
        ]);
    }

    /**
     * Reject a field value outside the allowed list.
     *
     * @param  array  $attributes
     * @param  string $field
     * @param  array  $allowed
     * @param  bool   $nullable
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function assertOneOf(array $attributes, $field, array $allowed, $nullable)
    {
        if (!array_key_exists($field, $attributes)) {
            return;
        }
        if ($nullable && $this->isBlank($attributes, $field)) {
            return;
        }
        if (!in_array($attributes[$field], $allowed, true)) {
            $this->reject(ucfirst($field) . ' must be one of: ' . implode(', ', $allowed) . '.');
        }
    }

    /**
     * Reject a field that is set but is not a JSON object.
     *
     * @param  array  $attributes
     * @param  string $field
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function assertJsonObject(array $attributes, $field)
    {
        if ($this->isBlank($attributes, $field)) {
            return;
        }

        $decoded = is_string($attributes[$field]) ? json_decode($attributes[$field]) : null;
        if (!is_object($decoded)) {
            $this->reject(ucfirst($field) . ' must be a JSON object.');
        }
    }

    /**
     * Reject a field that is set but is not an http or https URL with a host.
     *
     * @param  array  $attributes
     * @param  string $field
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function assertHttpUrl(array $attributes, $field)
    {
        if ($this->isBlank($attributes, $field)) {
            return;
        }

        $url = is_string($attributes[$field]) ? $attributes[$field] : '';
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || (string) parse_url($url, PHP_URL_HOST) === '') {
            $this->reject(ucfirst($field) . ' must be an http or https URL.');
        }
    }

    /**
     * Whether a field is missing, null or an empty string.
     *
     * @param  array  $attributes
     * @param  string $field
     * @return bool
     */
    private function isBlank(array $attributes, $field)
    {
        return !isset($attributes[$field]) || (string) $attributes[$field] === '';
    }

    /**
     * @param  string $message
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function reject($message)
    {
        throw new \Gaia\Exception\Permission($message);
    }
}
