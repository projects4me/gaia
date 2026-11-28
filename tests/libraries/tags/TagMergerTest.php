<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Tags;

use Gaia\Libraries\Tags\TagLinks;
use Gaia\Libraries\Tags\TagMergeRefusal;
use Gaia\Libraries\Tags\TagMerger;
use Gaia\Libraries\Tags\Tags;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TagMerger over in-memory tags. The SQL and the transaction
 * are covered by the api-tester cases.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagMergerTest extends TestCase
{
    /**
     * @var MergerFakeTags
     */
    private $tags;

    /**
     * @var MergerFakeLinks
     */
    private $links;

    protected function setUp(): void
    {
        $this->tags = new MergerFakeTags();
        $this->tags->live = [
            't1' => new MergerFakeTag('t1', 'p1'),
            't2' => new MergerFakeTag('t2', 'p1'),
            't3' => new MergerFakeTag('t3', 'p2'),
            'legacy' => new MergerFakeTag('legacy', ''),
        ];
        $this->links = new MergerFakeLinks();
    }

    public function testMergeMovesTheTaggingsDeletesTheSourceAndReturnsTheTarget()
    {
        $target = $this->merger()->merge('t1', 't2');

        $this->assertSame('t2', $target->id);
        $this->assertSame([['t1', 't2']], $this->links->moved);
        $this->assertTrue($this->tags->live['t1']->deleted);
        $this->assertFalse($this->tags->live['t2']->deleted);
    }

    public function testMergeRefusesAMissingSourceWith404()
    {
        $this->assertRefused('gone', 't2', TagMerger::SOURCE_MISSING, 404);
    }

    public function testMergeRefusesAMissingOrDeletedTarget()
    {
        $this->assertRefused('t1', 'gone', TagMerger::TARGET_MISSING, 422);
        $this->assertRefused('t1', '', TagMerger::TARGET_MISSING, 422);
    }

    public function testMergeRefusesTheSameTag()
    {
        $this->assertRefused('t1', 't1', TagMerger::SAME_TAG, 422);
    }

    public function testMergeRefusesATargetInAnotherProject()
    {
        $this->assertRefused('t1', 't3', TagMerger::OTHER_PROJECT, 422);
    }

    public function testMergeRefusesASourceWithoutAProject()
    {
        $this->assertRefused('legacy', 't1', TagMerger::OTHER_PROJECT, 422);
    }

    public function testMergeRefusesWhenTheSourceCanNotBeDeleted()
    {
        $this->tags->live['t1']->deletes = false;

        $this->assertRefused('t1', 't2', 'Model was already deleted', 422);
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $message
     * @param int    $code
     */
    private function assertRefused($source, $target, $message, $code)
    {
        try {
            $this->merger()->merge($source, $target);
            $this->fail('The merge was not refused.');
        } catch (TagMergeRefusal $refusal) {
            $this->assertSame($message, $refusal->getMessage());
            $this->assertSame($code, $refusal->getCode());
        }
        if ($message !== 'Model was already deleted') {
            $this->assertSame([], $this->links->moved);
        }
    }

    /**
     * @return TagMerger
     */
    private function merger()
    {
        return new MergerWithoutTransaction($this->tags, $this->links);
    }
}

/**
 * A merger that runs the work without a database transaction.
 */
class MergerWithoutTransaction extends TagMerger
{
    protected function inTransaction($tag, callable $work)
    {
        return (bool) $work();
    }
}

/**
 * Tags looked up from memory.
 */
class MergerFakeTags extends Tags
{
    /**
     * @var MergerFakeTag[]
     */
    public $live = [];

    public function findLive($tagId)
    {
        return $this->live[(string) $tagId] ?? null;
    }
}

/**
 * Records the moves instead of running SQL.
 */
class MergerFakeLinks extends TagLinks
{
    /**
     * @var array
     */
    public $moved = [];

    public function moveAll($sourceTagId, $targetTagId)
    {
        $this->moved[] = [$sourceTagId, $targetTagId];
        return true;
    }
}

/**
 * A tag with the fields and calls the merger uses.
 */
class MergerFakeTag
{
    public $id;
    public $projectId;
    public $deleted = false;
    public $deletes = true;

    public function __construct($id, $projectId)
    {
        $this->id = $id;
        $this->projectId = $projectId;
    }

    public function delete()
    {
        if (!$this->deletes) {
            return false;
        }
        $this->deleted = true;
        return true;
    }

    public function getMessages()
    {
        return $this->deletes ? [] : [new \Phalcon\Messages\Message('Model was already deleted')];
    }
}
