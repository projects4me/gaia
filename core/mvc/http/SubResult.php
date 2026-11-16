<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

/**
 * The captured outcome of one sub-request: status, decoded body, duration.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
final class SubResult
{
    /**
     * @var int
     */
    private $status;

    /**
     * @var mixed
     */
    private $body;

    /**
     * @var float
     */
    private $durationMs;

    /**
     * @param int $status HTTP status code
     * @param mixed $body the decoded JSON body; the raw text when it is not JSON; null when empty
     * @param float $durationMs wall time of the call in milliseconds
     */
    public function __construct(int $status, $body, float $durationMs)
    {
        $this->status = $status;
        $this->body = $body;
        $this->durationMs = $durationMs;
    }

    /**
     * Build a result from a response's status and content.
     *
     * @param \Phalcon\Http\ResponseInterface $response
     * @param float $durationMs
     * @return self
     */
    public static function fromResponse(\Phalcon\Http\ResponseInterface $response, float $durationMs): self
    {
        $content = (string) $response->getContent();
        $body = null;
        if ($content !== '') {
            $decoded = json_decode($content, true);
            $body = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
        }

        return new self((int) ($response->getStatusCode() ?? 200), $body, $durationMs);
    }

    /**
     * @return int
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return mixed
     */
    public function body()
    {
        return $this->body;
    }

    /**
     * @return float
     */
    public function durationMs(): float
    {
        return $this->durationMs;
    }
}
