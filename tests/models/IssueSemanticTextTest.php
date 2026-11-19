<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SemanticRecords;
use Gaia\Libraries\Search\SemanticReindexer;
use Gaia\Libraries\Search\SemanticText;
use Gaia\MVC\Models\Issue;
use Gaia\MVC\REST\Controllers\Components\Events\SearchLiveEvents;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;

/**
 * Issue keeps its semantic text current (agaia-4658): an ORM save stores the text and hash in the same write, and
 * the search reindex backfills every issue and announces only those without an up-to-date embedding.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IssueSemanticTextTest extends TestCase
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
     * An edit of the description stores the new text and hash with the edit.
     *
     * @return void
     */
    public function testAnEditStoresTheTextAndHash()
    {
        $issue = Issue::findFirst(array('conditions' => 'deleted = 0'));
        $this->assertNotFalse($issue, 'The test database has no issue to update');

        $this->db->begin();
        try {
            $issue->description = '<p>Semantic <b>text</b> check</p>';
            $this->assertTrue($issue->update(), implode('; ', array_map('strval', $issue->getMessages())));

            $row = $this->row($issue->id);
            $text = SemanticText::forBlock(array('semantic' => array('subject', 'description')))->text($row);
            $this->assertStringEndsWith("\nSemantic text check", $text);
            $this->assertSame($text, $row['searchSemanticText']);
            $this->assertSame(SemanticText::hashOf($text), $row['searchSemanticHash']);
        } finally {
            $this->db->rollback();
        }
    }

    /**
     * After the reindex every issue has its text and hash, and only issues without an up-to-date embedding were
     * announced.
     *
     * @return void
     */
    public function testTheReindexBackfillsEveryIssueAndPublishesOnlyStaleOnes()
    {
        $this->db->begin();
        try {
            $ids = array_column($this->db->fetchAll(
                'SELECT "id" FROM "issues" WHERE "deleted"::int = 0 ORDER BY "id" LIMIT 2',
                \Phalcon\Db\Enum::FETCH_ASSOC
            ), 'id');
            $this->assertCount(2, $ids, 'The test database needs two issues');
            // One issue has no text yet; the other was embedded from its current text
            $this->db->execute(
                'UPDATE "issues" SET "searchSemanticText" = NULL, "searchSemanticHash" = NULL WHERE "id" = ?',
                array($ids[0])
            );
            $this->db->execute(
                'UPDATE "issues" SET "searchEmbeddingHash" = ? WHERE "id" = ?',
                array(SemanticText::hashOf(SemanticText::forBlock(array('semantic' => array('subject', 'description')))
                    ->text($this->row($ids[1]))), $ids[1])
            );

            $posted = array();
            $metaManager = \Phalcon\Di::getDefault()->get('metaManager');
            $counts = (new SemanticReindexer(
                new SearchableRegistry($metaManager, array('Issue')),
                $metaManager,
                new SemanticRecords(),
                new SearchLiveEvents(new HermesPublisher(function ($envelope) use (&$posted) {
                    $posted[] = $envelope;
                })),
                function () {
                }
            ))->run('issue');

            $missing = $this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM "issues" WHERE "deleted"::int = 0 AND "searchSemanticHash" IS NULL',
                \Phalcon\Db\Enum::FETCH_ASSOC
            );
            $this->assertSame(0, (int) $missing['n']);
            $stale = $this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM "issues" WHERE "deleted"::int = 0'
                . ' AND "searchEmbeddingHash" IS DISTINCT FROM "searchSemanticHash"',
                \Phalcon\Db\Enum::FETCH_ASSOC
            );
            $published = array_column(array_column($posted, 'changes'), 'recordId');
            $this->assertSame((int) $stale['n'], $counts['issue']['published']);
            $this->assertCount((int) $stale['n'], $published);
            $this->assertContains($ids[0], $published);
            $this->assertNotContains($ids[1], $published);
        } finally {
            $this->db->rollback();
        }
    }

    /**
     * @param string $id
     * @return array
     */
    private function row($id)
    {
        return $this->db->fetchOne(
            'SELECT "subject", "description", "searchSemanticText", "searchSemanticHash" FROM "issues" WHERE "id" = ?',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            array($id)
        );
    }
}
