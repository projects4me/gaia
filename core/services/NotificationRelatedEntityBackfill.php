<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Services;

use Phalcon\Db\Adapter\AdapterInterface;

/**
 * One-off, idempotent backfill of system_notifications."relatedTo"/"relatedId" for notifications
 * created before those columns existed (agaia-4064). Only rows whose "relatedId" is NULL are
 * touched, and only those two columns are written; rows that can't be resolved stay NULL.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Services
 * @category Notifications
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationRelatedEntityBackfill
{
    /**
     * Rows read per batch.
     *
     * @var int
     */
    private const BATCH_SIZE = 500;

    /**
     * @var AdapterInterface
     */
    protected $db;

    /**
     * @var NotificationRelatedEntityResolver
     */
    protected $resolver;

    /**
     * @param AdapterInterface                  $db
     * @param NotificationRelatedEntityResolver $resolver
     */
    public function __construct(AdapterInterface $db, NotificationRelatedEntityResolver $resolver)
    {
        $this->db = $db;
        $this->resolver = $resolver;
    }

    /**
     * Fill relatedTo/relatedId on every notification that lacks them.
     *
     * @return array ['updated' => int, 'skipped' => int, 'unresolved' => int], where skipped counts
     *               the notifications that already had relatedId set
     */
    public function run()
    {
        $counts = ['updated' => 0, 'skipped' => $this->countAlreadySet(), 'unresolved' => 0];

        // Unresolved rows stay NULL, so page by id instead of re-reading "relatedId IS NULL" from the start.
        $lastId = '';
        while ($rows = $this->fetchBatch($lastId)) {
            foreach ($rows as $row) {
                $lastId = $row['id'];
                $related = $this->resolver->resolve($row['description'], $row['context']);
                if ($related === null) {
                    $counts['unresolved']++;
                    continue;
                }
                if ($this->update($row['id'], $related)) {
                    $counts['updated']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @return int
     */
    protected function countAlreadySet()
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM system_notifications WHERE "relatedId" IS NOT NULL',
            \PDO::FETCH_ASSOC
        );

        return (int) $row['total'];
    }

    /**
     * @param  string $afterId
     * @return array
     */
    protected function fetchBatch($afterId)
    {
        return $this->db->fetchAll(
            'SELECT id, description, context FROM system_notifications'
            . ' WHERE "relatedId" IS NULL AND id > ? ORDER BY id LIMIT ' . self::BATCH_SIZE,
            \PDO::FETCH_ASSOC,
            [$afterId]
        );
    }

    /**
     * Set relatedTo/relatedId, guarded on "relatedId" IS NULL so a concurrent write is never overwritten.
     *
     * @param  string $id
     * @param  array  $related ['relatedTo' => string, 'relatedId' => string]
     * @return bool Whether the row was updated
     */
    protected function update($id, array $related)
    {
        $this->db->execute(
            'UPDATE system_notifications SET "relatedTo" = ?, "relatedId" = ? WHERE id = ? AND "relatedId" IS NULL',
            [$related['relatedTo'], $related['relatedId'], $id]
        );

        return $this->db->affectedRows() > 0;
    }
}
