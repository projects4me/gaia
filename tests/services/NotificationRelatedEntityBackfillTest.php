<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Services;

use Gaia\Services\NotificationRelatedEntityBackfill;
use Gaia\Services\NotificationRelatedEntityResolver;
use Gaia\Services\NotificationRelatedRecordFinder;
use PHPUnit\Framework\TestCase;

/**
 * The backfill fills relatedTo/relatedId on notifications stored before those columns existed,
 * leaves everything else alone and changes nothing when run again (agaia-4117).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationRelatedEntityBackfillTest extends TestCase
{
    /**
     * Prefix of the notification ids this test inserts.
     *
     * @var string
     */
    private const ID_PREFIX = 'test-4117-';

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface
     */
    private $db;

    protected function setUp(): void
    {
        $this->db = \Phalcon\Di::getDefault()->get('db');
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
    }

    public function testFillsEachKindAndIsIdempotent()
    {
        $this->insert('issue', '{{User@u}} has assigned issue {{Issue@issue-a}} to you', ['relatedTo' => 'issue', 'issueNumber' => 1]);
        $this->insert('comment', '{{User@u}} has {{Comment@c}} on {{Issue@issue-b}}', ['relatedTo' => 'comment', 'commentId' => 'c']);
        $this->insert('timelog', '{{User@u}} has {{Timelog@t}} time on {{Issue@issue-c}}', ['relatedTo' => 'timelog', 'timelogId' => 't']);
        $this->insert('milestone', '{{User@u}} has created {{Milestone@ms-a}} milestone for {{Project@p}}', ['relatedTo' => 'milestone', 'milestoneId' => 'ms-a']);
        $this->insert('membership', '{{createdUser:u}} has added you to {{Project@project-a}}', ['relatedTo' => 'project', 'projectShortcode' => 'zz4117']);
        $this->insert('unresolved', 'something happened', ['relatedTo' => 'wiki']);
        $this->insert('already-set', '{{User@u}} has assigned issue {{Issue@issue-z}} to you', ['relatedTo' => 'issue'], 'project', 'kept');

        $before = $this->rows();
        $first = $this->backfill()->run();

        $rows = $this->rows();
        $this->assertSame(['issue', 'issue-a'], $rows['issue']['related']);
        $this->assertSame(['issue', 'issue-b'], $rows['comment']['related']);
        $this->assertSame(['issue', 'issue-c'], $rows['timelog']['related']);
        $this->assertSame(['milestone', 'ms-a'], $rows['milestone']['related']);
        $this->assertSame(['project', 'project-a'], $rows['membership']['related']);
        $this->assertSame([null, null], $rows['unresolved']['related']);
        $this->assertSame(['project', 'kept'], $rows['already-set']['related']);

        foreach ($before as $key => $row) {
            $this->assertSame($row['untouched'], $rows[$key]['untouched'], "$key: only relatedTo/relatedId change");
        }

        $this->assertGreaterThanOrEqual(5, $first['updated']);
        $this->assertGreaterThanOrEqual(1, $first['skipped']);
        $this->assertGreaterThanOrEqual(1, $first['unresolved']);

        $second = $this->backfill()->run();

        $this->assertSame($rows, $this->rows());
        $this->assertSame(0, $second['updated']);
        $this->assertSame($first['skipped'] + $first['updated'], $second['skipped']);
        $this->assertSame($first['unresolved'], $second['unresolved']);
    }

    public function testFinderLooksUpRecordsByShortcode()
    {
        $finder = new NotificationRelatedRecordFinder($this->db);

        $this->assertNull($finder->findProjectId('no-such-shortcode-4117'));
        $this->assertNull($finder->findIssueId('no-such-shortcode-4117', 1));

        $issue = $this->db->fetchOne(
            'SELECT i.id, i."issueNumber", p.id AS "projectId", p."shortCode" FROM issues i'
            . ' JOIN projects p ON p.id = i."projectId" WHERE p."shortCode" IS NOT NULL LIMIT 1',
            \PDO::FETCH_ASSOC
        );
        if (!$issue) {
            $this->markTestSkipped('No issue with a project short code in the test database');
        }

        $this->assertSame($issue['projectId'], $finder->findProjectId($issue['shortCode']));
        $this->assertSame($issue['id'], $finder->findIssueId($issue['shortCode'], $issue['issueNumber']));
    }

    public function testBackfillIsAvailableOnTheCli()
    {
        $this->assertTrue(method_exists(\NotificationTask::class, 'backfillRelatedAction'));
    }

    /**
     * @return NotificationRelatedEntityBackfill
     */
    private function backfill()
    {
        return new NotificationRelatedEntityBackfill(
            $this->db,
            new NotificationRelatedEntityResolver(new NotificationRelatedRecordFinder($this->db))
        );
    }

    /**
     * @param string      $key
     * @param string      $description
     * @param array       $context
     * @param string|null $relatedTo
     * @param string|null $relatedId
     */
    private function insert($key, $description, array $context, $relatedTo = null, $relatedId = null)
    {
        $this->db->execute(
            'INSERT INTO system_notifications (id, "dateCreated", "dateModified", description, "createdUser",'
            . ' "createdUserName", "modifiedUser", "modifiedUserName", context, "relatedTo", "relatedId")'
            . " VALUES (?, '2025-06-01 10:00:00', '2025-06-01 10:00:00', ?, 'u', 'User', 'u', 'User', ?, ?, ?)",
            [self::ID_PREFIX . $key, $description, json_encode($context), $relatedTo, $relatedId]
        );
    }

    /**
     * The test's notifications keyed by name: their related pair and every other column.
     *
     * @return array
     */
    private function rows()
    {
        $rows = [];
        foreach ($this->db->fetchAll(
            'SELECT * FROM system_notifications WHERE id LIKE ? ORDER BY id',
            \PDO::FETCH_ASSOC,
            [self::ID_PREFIX . '%']
        ) as $row) {
            $key = substr($row['id'], strlen(self::ID_PREFIX));
            $related = [$row['relatedTo'], $row['relatedId']];
            unset($row['relatedTo'], $row['relatedId']);
            $rows[$key] = ['related' => $related, 'untouched' => $row];
        }

        return $rows;
    }

    private function purge()
    {
        $this->db->execute('DELETE FROM system_notifications WHERE id LIKE ?', [self::ID_PREFIX . '%']);
    }
}
