<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Conversations;

use Gaia\Libraries\Conversations\RoomActivities;
use PHPUnit\Framework\TestCase;

/**
 * Tests which activities a conversation-room change records.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RoomActivitiesTest extends TestCase
{
    /**
     * The policy, with issue numbers looked up from a fixed map.
     *
     * @return RoomActivities
     */
    private function policy()
    {
        return new RoomActivities(function ($id) {
            $numbers = array('issue-a' => 11, 'issue-b' => 22);
            return isset($numbers[$id]) ? $numbers[$id] : null;
        });
    }

    public function testCreateRecordsThreadStarted()
    {
        $activities = $this->policy()->forCreate();

        $this->assertCount(1, $activities);
        $this->assertSame('created', $activities[0]['type']);
        $this->assertSame('Thread started', $activities[0]['description']);
        $this->assertSame(array(), $activities[0]['context']);
    }

    public function testCreateWithAnIssueKeepsTheIssueInTheContext()
    {
        $activities = $this->policy()->forCreate('issue-a');

        $this->assertSame(array('issueId' => 'issue-a', 'issueNumber' => 11), $activities[0]['context']);
    }

    public function testSubjectEditRecordsOldAndNew()
    {
        $activities = $this->policy()->forUpdate(
            array('subject' => array('old' => 'Old', 'new' => 'New'))
        );

        $this->assertCount(1, $activities);
        $this->assertSame('updated', $activities[0]['type']);
        $this->assertSame('Subject edited', $activities[0]['description']);
        $this->assertSame(array('field' => 'subject', 'old' => 'Old', 'new' => 'New'), $activities[0]['context']);
    }

    public function testDescriptionEditRecordsOnlyTheField()
    {
        $activities = $this->policy()->forUpdate(
            array('description' => array('old' => 'a', 'new' => 'b'))
        );

        $this->assertCount(1, $activities);
        $this->assertSame('Description edited', $activities[0]['description']);
        $this->assertSame(array('field' => 'description'), $activities[0]['context']);
    }

    public function testIssueSetRecordsLinked()
    {
        $activities = $this->policy()->forUpdate(
            array('issueId' => array('old' => null, 'new' => 'issue-a'))
        );

        $this->assertSame('linked', $activities[0]['type']);
        $this->assertSame('Linked issue {{Issue@11}}', $activities[0]['description']);
        $this->assertSame(11, $activities[0]['context']['newIssueNumber']);
        $this->assertNull($activities[0]['context']['oldIssueId']);
    }

    public function testIssueChangeRecordsBothIssues()
    {
        $activities = $this->policy()->forUpdate(
            array('issueId' => array('old' => 'issue-a', 'new' => 'issue-b'))
        );

        $this->assertSame('linked', $activities[0]['type']);
        $this->assertSame(
            'Changed linked issue from {{Issue@11}} to {{Issue@22}}',
            $activities[0]['description']
        );
    }

    public function testIssueClearedRecordsUnlinked()
    {
        $activities = $this->policy()->forUpdate(
            array('issueId' => array('old' => 'issue-a', 'new' => ''))
        );

        $this->assertSame('unlinked', $activities[0]['type']);
        $this->assertSame('Unlinked issue {{Issue@11}}', $activities[0]['description']);
        $this->assertNull($activities[0]['context']['newIssueId']);
    }

    public function testUnwatchedAndUnchangedFieldsRecordNothing()
    {
        $activities = $this->policy()->forUpdate(
            array(
                'dateModified' => array('old' => '2026-01-01', 'new' => '2026-01-02'),
                'subject' => array('old' => 'Same', 'new' => 'Same'),
                'issueId' => array('old' => null, 'new' => ''),
            )
        );

        $this->assertSame(array(), $activities);
    }

    public function testSeveralChangesRecordOneActivityEach()
    {
        $activities = $this->policy()->forUpdate(
            array(
                'subject' => array('old' => 'A', 'new' => 'B'),
                'description' => array('old' => 'x', 'new' => 'y'),
                'issueId' => array('old' => null, 'new' => 'issue-b'),
            )
        );

        $this->assertSame(array('updated', 'updated', 'linked'), array_column($activities, 'type'));
    }

    public function testIssueGoingFromEmptyToNullRecordsNothing()
    {
        $activities = $this->policy()->forUpdate(array('issueId' => array('old' => '', 'new' => null)));

        $this->assertSame(array(), $activities);
    }

    public function testUnknownIssueLeavesTheReferenceOut()
    {
        $activities = $this->policy()->forUpdate(array('issueId' => array('old' => null, 'new' => 'gone')));

        $this->assertSame('Linked issue', $activities[0]['description']);
        $this->assertNull($activities[0]['context']['newIssueNumber']);
    }
}
