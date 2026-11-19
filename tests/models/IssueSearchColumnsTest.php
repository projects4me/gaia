<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Issue;

/**
 * Issue is searchable (agaia-4656): its table has the generated searchVector and the search columns that have their
 * own writer, so the ORM must leave them out of every INSERT and UPDATE, and the database keeps searchVector current.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IssueSearchColumnsTest extends TestCase
{
    /**
     * The database connection.
     *
     * @var \Phalcon\Db\Adapter\AdapterInterface
     */
    private $db;

    protected function setUp(): void
    {
        $db = \Phalcon\Di::getDefault()->getShared('db');
        if ($db->getType() !== 'pgsql') {
            $this->markTestSkipped('Search columns need a PostgreSQL database');
        }
        $this->db = $db;

        // No one is logged in: the user behaviours write the system user
        global $currentUser;
        $currentUser = new \stdClass();
    }

    /**
     * A static query is the first use of Issue in a request (e.g. issue counts by status) and still works.
     *
     * @return void
     */
    public function testStaticQueryIsTheFirstUseOfTheModel()
    {
        $this->assertIsInt(Issue::count(array('conditions' => 'deleted = 0')));
    }

    /**
     * An ORM update leaves the generated column to the database, which recomputes it from the subject.
     *
     * @return void
     */
    public function testUpdateLeavesTheGeneratedSearchVectorToTheDatabase()
    {
        $issue = Issue::findFirst(array('conditions' => 'deleted = 0'));
        $this->assertNotFalse($issue, 'The test database has no issue to update');

        $this->db->begin();
        try {
            $issue->subject = 'Zebracorn search vector check';
            $this->assertTrue($issue->update(), implode('; ', array_map('strval', $issue->getMessages())));

            $row = $this->db->fetchOne(
                'SELECT "searchVector"::text AS v FROM "issues" WHERE "id" = ?',
                \Phalcon\Db\Enum::FETCH_ASSOC,
                array($issue->id)
            );
            $this->assertStringContainsString("'zebracorn':1A", $row['v']);
        } finally {
            // Undo the update and anything its behaviours wrote
            $this->db->rollback();
        }
    }

    /**
     * The indexer writes the embedding columns on its own: an ORM update of a model loaded before that write keeps it.
     *
     * @return void
     */
    public function testUpdateKeepsANewerIndexerWrite()
    {
        $issue = Issue::findFirst(array('conditions' => 'deleted = 0'));
        $this->assertNotFalse($issue, 'The test database has no issue to update');

        $this->db->begin();
        try {
            // The indexer writes after the model was loaded
            $this->db->execute(
                'UPDATE "issues" SET "searchActualHash" = ? WHERE "id" = ?',
                array('indexer-hash-4656', $issue->id)
            );

            $issue->subject = 'Stale model update';
            $this->assertTrue($issue->update(), implode('; ', array_map('strval', $issue->getMessages())));

            $row = $this->db->fetchOne(
                'SELECT "searchActualHash" AS h FROM "issues" WHERE "id" = ?',
                \Phalcon\Db\Enum::FETCH_ASSOC,
                array($issue->id)
            );
            $this->assertSame('indexer-hash-4656', $row['h']);
        } finally {
            // Undo the update and anything its behaviours wrote
            $this->db->rollback();
        }
    }
}
