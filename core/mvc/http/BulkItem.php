<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

/**
 * One validated call of a bulk batch: the client's id, the method, the URL
 * and the optional JSON body (see BulkRequestValidator).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
final class BulkItem
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $url;

    /**
     * @var array|\stdClass|null
     */
    private $body;

    /**
     * @param string $id the client's id, unique within the batch
     * @param string $method GET, POST, PATCH or DELETE
     * @param string $url relative /api/v1/... path with an optional query string
     * @param array|\stdClass|null $body the JSON body decoded with objects as
     *   stdClass (so {} stays apart from []); null for none
     */
    public function __construct(string $id, string $method, string $url, $body)
    {
        $this->id = $id;
        $this->method = $method;
        $this->url = $url;
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * @return array|\stdClass|null
     */
    public function body()
    {
        return $this->body;
    }
}
