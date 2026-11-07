<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Services;

use Phalcon\Db\Adapter\AdapterInterface;

/**
 * Looks up the records a system notification refers to when its context names them only by
 * project short code and issue number (instead of by id).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Services
 * @category Notifications
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationRelatedRecordFinder
{
    /**
     * @var AdapterInterface
     */
    protected $db;

    /**
     * @param AdapterInterface $db
     */
    public function __construct(AdapterInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Find the id of an issue by its project's short code and its issue number.
     *
     * @param  string     $projectShortcode
     * @param  int|string $issueNumber
     * @return string|null
     */
    public function findIssueId($projectShortcode, $issueNumber)
    {
        $row = $this->db->fetchOne(
            'SELECT i.id FROM issues i JOIN projects p ON p.id = i."projectId"'
            . ' WHERE p."shortCode" = ? AND i."issueNumber" = ? AND i.deleted = 0 AND p.deleted = 0 LIMIT 1',
            \PDO::FETCH_ASSOC,
            [(string) $projectShortcode, (int) $issueNumber]
        );

        return $row ? $row['id'] : null;
    }

    /**
     * Find the id of a project by its short code.
     *
     * @param  string $projectShortcode
     * @return string|null
     */
    public function findProjectId($projectShortcode)
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM projects WHERE "shortCode" = ? AND deleted = 0 LIMIT 1',
            \PDO::FETCH_ASSOC,
            [(string) $projectShortcode]
        );

        return $row ? $row['id'] : null;
    }
}
