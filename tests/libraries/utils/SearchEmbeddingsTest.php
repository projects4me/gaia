<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Exception\BadRequest;
use Gaia\Exception\Conflict;
use Gaia\Exception\ResourceNotFound;
use Gaia\Libraries\Search\EmbeddingWrite;
use Gaia\Libraries\Search\SearchEmbeddings;
use PHPUnit\Framework\TestCase;

/**
 * The Ares embedding write (D-072): the body is the embedding and its hash alone, and the embedding is stored only
 * if, with the row locked, its hash is still the record's semantic hash.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchEmbeddingsTest extends TestCase
{
    const ID = 'issue-0001';

    /**
     * @var string
     */
    private $hash;

    protected function setUp(): void
    {
        $this->hash = hash('sha256', 'Subject');
    }

    public function testTheCurrentHashLocksTheRowAndStoresTheEmbeddingAlone()
    {
        $db = $this->db(['hash' => $this->hash]);

        $embeddedAt = (new SearchEmbeddings($db))->store('issues', $this->write());

        $this->assertSame(['begin', 'fetchOne', 'execute', 'commit'], $db->calls);
        $this->assertStringContainsString('FOR UPDATE', $db->sql[0]);
        $this->assertSame([self::ID], $db->binds[0]);
        $this->assertSame(
            'UPDATE "issues" SET "searchEmbedding" = CAST(? AS vector), "searchEmbeddingHash" = ?, '
            . '"searchEmbeddedAt" = ? WHERE "id" = ?',
            $db->sql[1]
        );
        $this->assertSame('[' . implode(',', array_fill(0, 768, '0.25')) . ']', $db->binds[1][0]);
        $this->assertSame([$this->hash, $embeddedAt, self::ID], array_slice($db->binds[1], 1));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $embeddedAt);
    }

    public function testAnOlderHashIsAConflictAndWritesNothing()
    {
        $db = $this->db(['hash' => hash('sha256', 'Subject renamed')]);

        try {
            (new SearchEmbeddings($db))->store('issues', $this->write());
            $this->fail('A stale hash must not be stored');
        } catch (Conflict $e) {
            $this->assertSame(SearchEmbeddings::STALE_ERROR, $e->getMessage());
        }
        $this->assertSame(['begin', 'fetchOne', 'rollback'], $db->calls);
    }

    public function testARecordWithoutSemanticTextIsAConflict()
    {
        $db = $this->db(['hash' => null]);

        $this->expectException(Conflict::class);
        (new SearchEmbeddings($db))->store('issues', $this->write());
    }

    public function testAMissingOrDeletedRecordIsNotFound()
    {
        $db = $this->db(false);

        try {
            (new SearchEmbeddings($db))->store('issues', $this->write());
            $this->fail('A missing record must be 404');
        } catch (ResourceNotFound $e) {
            $this->assertSame(SearchEmbeddings::NOT_FOUND_ERROR, $e->getMessage());
        }
        $this->assertSame(['begin', 'fetchOne', 'rollback'], $db->calls);
        $this->assertStringContainsString('"deleted"::int = 0', $db->sql[0]);
    }

    public function testAFailedUpdateRollsBack()
    {
        $db = $this->db(['hash' => $this->hash], true);

        try {
            (new SearchEmbeddings($db))->store('issues', $this->write());
            $this->fail('The failure must reach the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame(['begin', 'fetchOne', 'execute', 'rollback'], $db->calls);
        }
    }

    public function testAFailedRollbackDoesNotHideTheFailure()
    {
        $db = $this->db(['hash' => $this->hash], true, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('update failed');
        (new SearchEmbeddings($db))->store('issues', $this->write());
    }

    public function testTheWriteTakesTheEmbeddingAndItsHash()
    {
        $write = $this->write();

        $this->assertSame(self::ID, $write->id());
        $this->assertSame($this->hash, $write->hash());
        $this->assertSame(array_fill(0, 768, 0.25), $write->embedding());
        $this->assertSame(self::ID, EmbeddingWrite::fromAttributes($this->attributes() + ['id' => self::ID])->id());
        $this->assertSame(self::ID, EmbeddingWrite::fromAttributes($this->attributes(), self::ID)->id());
    }

    /**
     * @dataProvider invalidWrites
     */
    public function testAnInvalidWriteIsRefused(callable $break, $message)
    {
        $attributes = $this->attributes() + ['id' => self::ID];
        $urlId = self::ID;
        $break($attributes, $urlId);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        EmbeddingWrite::fromAttributes($attributes, $urlId);
    }

    public function invalidWrites()
    {
        return [
            'another field' => [function (&$a) {
                $a['subject'] = 'Changed';
            }, EmbeddingWrite::FIELDS_ERROR],
            'subject alone' => [function (&$a) {
                $a = ['id' => SearchEmbeddingsTest::ID, 'subject' => 'Changed'];
            }, EmbeddingWrite::FIELDS_ERROR],
            'no hash' => [function (&$a) {
                unset($a['searchEmbeddingHash']);
            }, EmbeddingWrite::FIELDS_ERROR],
            'no id in the URL or the body' => [function (&$a, &$urlId) {
                unset($a['id']);
                $urlId = null;
            }, EmbeddingWrite::ID_ERROR],
            'another id than the URL' => [function (&$a, &$urlId) {
                $urlId = 'issue-0002';
            }, EmbeddingWrite::ID_ERROR],
            'too short' => [function (&$a) {
                array_pop($a['searchEmbedding']);
            }, EmbeddingWrite::EMBEDDING_ERROR],
            'not a list' => [function (&$a) {
                $a['searchEmbedding'] = array_combine(range(1, 768), $a['searchEmbedding']);
            }, EmbeddingWrite::EMBEDDING_ERROR],
            'a string number' => [function (&$a) {
                $a['searchEmbedding'][3] = '0.5';
            }, EmbeddingWrite::EMBEDDING_ERROR],
            'not a hash' => [function (&$a) {
                $a['searchEmbeddingHash'] = 'abc';
            }, EmbeddingWrite::HASH_ERROR],
            'an upper case hash' => [function (&$a) {
                $a['searchEmbeddingHash'] = strtoupper($a['searchEmbeddingHash']);
            }, EmbeddingWrite::HASH_ERROR],
        ];
    }

    /**
     * @return array
     */
    private function attributes()
    {
        return ['searchEmbedding' => array_fill(0, 768, 0.25), 'searchEmbeddingHash' => hash('sha256', 'Subject')];
    }

    /**
     * @return EmbeddingWrite
     */
    private function write()
    {
        return EmbeddingWrite::fromAttributes($this->attributes() + ['id' => self::ID], self::ID);
    }

    /**
     * A database adapter that records its calls.
     *
     * @param array|false $row What the locking select returns
     * @param bool $failUpdate Whether the update throws
     * @param bool $failRollback Whether the rollback throws
     * @return object
     */
    private function db($row, $failUpdate = false, $failRollback = false)
    {
        return new class ($row, $failUpdate, $failRollback) {
            public $calls = [];
            public $sql = [];
            public $binds = [];
            private $row;
            private $failUpdate;
            private $failRollback;
            private $open = false;

            public function __construct($row, $failUpdate, $failRollback)
            {
                $this->row = $row;
                $this->failUpdate = $failUpdate;
                $this->failRollback = $failRollback;
            }

            public function begin()
            {
                $this->calls[] = 'begin';
                $this->open = true;
            }

            public function fetchOne($sql, $mode, array $binds)
            {
                $this->calls[] = 'fetchOne';
                $this->sql[] = $sql;
                $this->binds[] = $binds;
                return $this->row;
            }

            public function execute($sql, array $binds)
            {
                $this->calls[] = 'execute';
                $this->sql[] = $sql;
                $this->binds[] = $binds;
                if ($this->failUpdate) {
                    throw new \RuntimeException('update failed');
                }
                return true;
            }

            public function commit()
            {
                $this->calls[] = 'commit';
                $this->open = false;
            }

            public function rollback()
            {
                $this->calls[] = 'rollback';
                if ($this->failRollback) {
                    throw new \LogicException('no active transaction');
                }
                $this->open = false;
            }

            public function isUnderTransaction()
            {
                return $this->open;
            }
        };
    }
}
