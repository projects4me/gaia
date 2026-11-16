<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

/**
 * Runs a validated bulk batch, one call at a time in the order sent, through
 * the SubRequestRunner with the caller's Authorization header.
 *
 * Each call is authenticated and authorized on its own, and a failed call
 * affects only its own result. There is no rollback: an earlier write stays
 * when a later call fails. One log line per batch records the user, the item
 * count, the statuses and the total time, never the bodies.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BulkExecutor
{
    /**
     * @var SubRequestRunner
     */
    private $runner;

    /**
     * @var object|null a logger with info(string); null logs nothing
     */
    private $logger;

    /**
     * @param SubRequestRunner $runner runs one call in-process
     * @param object|null $logger a logger with info(string); null logs nothing
     */
    public function __construct(SubRequestRunner $runner, $logger = null)
    {
        $this->runner = $runner;
        $this->logger = $logger;
    }

    /**
     * Run every item and return one result per item, in request order.
     *
     * @param BulkItem[] $items the validated batch
     * @param string $authorization the caller's Authorization header
     * @param string|null $userId the caller, for the log line
     * @return array<int, array{id: string, status: int, body: mixed, durationMs: int}>
     */
    public function run(array $items, string $authorization, ?string $userId): array
    {
        $started = microtime(true);
        $responses = [];
        foreach ($items as $item) {
            $result = $this->runner->run($item->method(), $item->url(), $item->body(), $authorization);
            $responses[] = [
                'id' => $item->id(),
                'status' => $result->status(),
                'body' => $result->body(),
                'durationMs' => (int) round($result->durationMs()),
            ];
        }

        $this->log($responses, $userId, (microtime(true) - $started) * 1000);

        return $responses;
    }

    /**
     * One line per batch: user, item count, statuses, total time.
     *
     * @param array $responses
     * @param string|null $userId
     * @param float $totalMs
     * @return void
     */
    private function log(array $responses, ?string $userId, float $totalMs): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->info(sprintf(
            'Bulk user=%s items=%d statuses=%s totalMs=%d',
            $userId ?? '-',
            count($responses),
            implode(',', array_column($responses, 'status')),
            (int) round($totalMs)
        ));
    }
}
