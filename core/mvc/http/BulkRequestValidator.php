<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

use Gaia\Exception\BadRequest;

/**
 * Checks a whole bulk batch before any of its calls runs.
 *
 * The body must stay under the size limit and be `{"requests": [...]}` with
 * between one and the configured number of items. Each item has a non-empty
 * string `id` unique within the batch, a `method` of GET, POST, PATCH or
 * DELETE, a `url` the BulkRoutePolicy accepts and an optional JSON `body`.
 * The first failed check refuses the batch with a 400, so nothing runs.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BulkRequestValidator
{
    public const METHODS = ['GET', 'POST', 'PATCH', 'DELETE'];

    /**
     * @var BulkRoutePolicy
     */
    private $routes;

    /**
     * @var int
     */
    private $maxRequests;

    /**
     * @var int
     */
    private $maxBodyBytes;

    /**
     * @param BulkRoutePolicy $routes which URLs a batch may call
     * @param int $maxRequests the most items one batch may hold
     * @param int $maxBodyBytes the largest batch body, in bytes
     */
    public function __construct(BulkRoutePolicy $routes, int $maxRequests, int $maxBodyBytes)
    {
        $this->routes = $routes;
        $this->maxRequests = $maxRequests;
        $this->maxBodyBytes = $maxBodyBytes;
    }

    /**
     * The validator for the configured limits and routes (config/bulk.php).
     *
     * @param \Phalcon\Config $settings the application settings
     * @return self
     */
    public static function fromSettings($settings): self
    {
        return new self(
            BulkRoutePolicy::fromSettings($settings),
            (int) $settings->bulk->maxRequests,
            (int) $settings->bulk->maxBodyBytes
        );
    }

    /**
     * The batch's items, in the order sent.
     *
     * @param string $rawBody the bulk request's body
     * @return BulkItem[]
     * @throws BadRequest When any check fails
     */
    public function validate(string $rawBody): array
    {
        if (strlen($rawBody) > $this->maxBodyBytes) {
            throw new BadRequest("The bulk request body may not exceed {$this->maxBodyBytes} bytes");
        }

        $batch = json_decode($rawBody, true);
        $requests = is_array($batch) ? ($batch['requests'] ?? null) : null;
        if (!is_array($requests) || $requests === [] || array_keys($requests) !== range(0, count($requests) - 1)) {
            throw new BadRequest('requests must be a non-empty array');
        }

        if (count($requests) > $this->maxRequests) {
            throw new BadRequest("A bulk request may hold at most {$this->maxRequests} requests");
        }

        $items = [];
        $ids = [];
        foreach ($requests as $index => $request) {
            $item = $this->item($index, $request);
            if (isset($ids[$item->id()])) {
                throw new BadRequest("requests[{$index}].id '{$item->id()}' is used more than once");
            }
            $ids[$item->id()] = true;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * One item, checked on its own.
     *
     * @param int $index
     * @param mixed $request
     * @return BulkItem
     * @throws BadRequest
     */
    private function item(int $index, $request): BulkItem
    {
        $at = "requests[{$index}]";
        if (!is_array($request)) {
            throw new BadRequest("{$at} must be an object");
        }

        $id = $request['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new BadRequest("{$at}.id must be a non-empty string");
        }

        $method = $request['method'] ?? null;
        if (!is_string($method) || !in_array($method, self::METHODS, true)) {
            throw new BadRequest("{$at}.method must be one of " . implode(', ', self::METHODS));
        }

        $url = $request['url'] ?? null;
        if (!is_string($url)) {
            throw new BadRequest("{$at}.url must be a string");
        }
        $refusal = $this->routes->refusal($method, $url);
        if ($refusal !== null) {
            throw new BadRequest("{$at}.url: {$refusal}");
        }

        $body = $request['body'] ?? null;
        if ($body !== null && !is_array($body)) {
            throw new BadRequest("{$at}.body must be a JSON object, an array or null");
        }

        return new BulkItem($id, $method, $url, $body);
    }
}
