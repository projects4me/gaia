<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Mentions;

use Gaia\Libraries\Mentions\MentionParser;
use Gaia\Libraries\Mentions\MentionRecorder;
use Gaia\Libraries\Workflows\ProjectMembers;
use Gaia\MVC\REST\Controllers\Components\Events\Support\ProjectIdResolver;
use PHPUnit\Framework\TestCase;

/**
 * A record with mentions, standing in for a Gaia model.
 */
class Mentionedrecord
{
    public $id = 'rec-1';
    public $projectId = 'p-1';
    public $description = null;
    public $summary = null;
}

/**
 * Unit tests for MentionRecorder: append-only Mention rows for the active
 * project members a record mentions, never the actor.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MentionRecorderTest extends TestCase
{
    /**
     * Mention rows saved so far, as [relatedTo, relatedId, projectId, userId].
     *
     * @var array
     */
    private $rows = [];

    /**
     * Users who exist and are not deleted.
     *
     * @var array
     */
    private $activeUsers = ['actor', 'u-1', 'u-2', 'u-3', 'outsider'];

    /**
     * Members of project p-1.
     *
     * @var array
     */
    private $memberUsers = ['actor', 'u-1', 'u-2', 'u-3'];

    /**
     * Two mentions create two rows; saving again creates none; a third
     * mention edited in creates one.
     *
     * @return void
     */
    public function testAcceptance()
    {
        $recorder = $this->recorder();
        $record = new Mentionedrecord();
        $record->description = '<a href="/app/user/u-1">@A</a> <a href="/app/user/u-2">@B</a>';

        $this->assertSame(['u-1', 'u-2'], $recorder->record($record, ['description'], 'actor'));
        $this->assertSame([], $recorder->record($record, ['description'], 'actor'));

        $record->description .= ' [@C](/app/user/u-3)';
        $this->assertSame(['u-3'], $recorder->record($record, ['description'], 'actor'));

        $this->assertSame([
            ['mentionedrecord', 'rec-1', 'p-1', 'u-1'],
            ['mentionedrecord', 'rec-1', 'p-1', 'u-2'],
            ['mentionedrecord', 'rec-1', 'p-1', 'u-3'],
        ], $this->rows);
    }

    /**
     * Rows are append-only: removing a mention and adding it back records nothing.
     *
     * @return void
     */
    public function testRemovedAndReaddedMentionIsNotRecordedAgain()
    {
        $recorder = $this->recorder();
        $record = new Mentionedrecord();
        $record->description = '<a href="/app/user/u-1">@A</a>';
        $recorder->record($record, ['description'], 'actor');

        $record->description = 'gone';
        $this->assertSame([], $recorder->record($record, ['description'], 'actor'));
        $record->description = '<a href="/app/user/u-1">@A</a>';
        $this->assertSame([], $recorder->record($record, ['description'], 'actor'));
        $this->assertCount(1, $this->rows);
    }

    /**
     * Every configured field is read, and a user mentioned in two fields is recorded once.
     *
     * @return void
     */
    public function testAllFieldsAreRead()
    {
        $record = new Mentionedrecord();
        $record->summary = '[@B](/app/user/u-2)';
        $record->description = '<a href="/app/user/u-1">@A</a> [@B](/app/user/u-2)';

        $this->assertSame(['u-2', 'u-1'], $this->recorder()->record($record, ['summary', 'description'], 'actor'));
    }

    /**
     * The actor mentioning themselves is not recorded.
     *
     * @return void
     */
    public function testSelfMentionIsSkipped()
    {
        $record = new Mentionedrecord();
        $record->description = '<a href="/app/user/actor">@Me</a> <a href="/app/user/u-1">@A</a>';

        $this->assertSame(['u-1'], $this->recorder()->record($record, ['description'], 'actor'));
    }

    /**
     * Unknown or deleted users and users outside the project are not recorded.
     *
     * @return void
     */
    public function testUnknownAndNonMembersAreSkipped()
    {
        $record = new Mentionedrecord();
        $record->description = '[@X](/app/user/ghost) [@O](/app/user/outsider) [@A](/app/user/u-1)';

        $this->assertSame(['u-1'], $this->recorder()->record($record, ['description'], 'actor'));
    }

    /**
     * A record without a project records nobody.
     *
     * @return void
     */
    public function testRecordWithoutProjectRecordsNobody()
    {
        $record = new Mentionedrecord();
        $record->projectId = null;
        $record->description = '[@A](/app/user/u-1)';

        $this->assertSame([], $this->recorder()->record($record, ['description'], 'actor'));
        $this->assertSame([], $this->rows);
    }

    /**
     * A row that fails to save is not reported as recorded.
     *
     * @return void
     */
    public function testFailedSaveIsNotRecorded()
    {
        $recorder = $this->recorder(false);
        $record = new Mentionedrecord();
        $record->description = '[@A](/app/user/u-1)';

        $this->assertSame([], $recorder->record($record, ['description'], 'actor'));
    }

    /**
     * A recorder over in-memory users, members and rows.
     *
     * @param  bool $saves Whether saving a row succeeds.
     * @return MentionRecorder
     */
    private function recorder($saves = true)
    {
        $projects = $this->createMock(ProjectIdResolver::class);
        $projects->method('resolve')->willReturnCallback(function ($record) {
            return $record->projectId;
        });

        $members = $this->createMock(ProjectMembers::class);
        $members->method('isMember')->willReturnCallback(function ($projectId, $userId) {
            return $projectId === 'p-1' && in_array($userId, $this->memberUsers, true);
        });

        $recorder = $this->getMockBuilder(MentionRecorder::class)
            ->setConstructorArgs([new MentionParser(), $projects, $members])
            ->onlyMethods(['recordedUserIds', 'activeUserIds', 'save'])
            ->getMock();
        $recorder->method('recordedUserIds')->willReturnCallback(function ($relatedTo, $relatedId, $userIds) {
            $recorded = [];
            foreach ($this->rows as $row) {
                if ($row[0] === $relatedTo && $row[1] === $relatedId && in_array($row[3], $userIds, true)) {
                    $recorded[] = $row[3];
                }
            }
            return $recorded;
        });
        $recorder->method('activeUserIds')->willReturnCallback(function ($userIds) {
            return array_values(array_intersect($userIds, $this->activeUsers));
        });
        $recorder->method('save')->willReturnCallback(function ($relatedTo, $relatedId, $projectId, $userId) use ($saves) {
            if ($saves) {
                $this->rows[] = [$relatedTo, $relatedId, $projectId, $userId];
            }
            return $saves;
        });

        return $recorder;
    }
}
