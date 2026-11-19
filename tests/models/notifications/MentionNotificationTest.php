<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Controller\Components\Notifications;

use Gaia\MVC\REST\Controllers\Components\Notifications\Modules\MentionNotification;
use PHPUnit\Framework\TestCase;

/**
 * MentionNotification with the record lookups and writes replaced by fakes.
 */
class FakeMentionNotification extends MentionNotification
{
    public $records = [];
    public $created = [];
    public $marked = 0;

    protected function load($model, $id)
    {
        return isset($this->records[$model][$id]) ? (object) $this->records[$model][$id] : false;
    }

    protected function create(array $data)
    {
        $this->created[] = $data;

        return (object) ['id' => 'n-' . count($this->created)];
    }

    protected function markNotified($mention)
    {
        $this->marked++;
    }
}

/**
 * Recipient service that records who was notified.
 */
class FakeRecipients
{
    public $notified = [];

    public function notifyUsers($notification, $userIds)
    {
        $this->notified[] = [$notification->id, $userIds];
    }
}

/**
 * Unit tests for MentionNotification: one notification per mention, for each
 * relatedTo, to the mentioned user only.
 *
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MentionNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        global $currentUser;
        $currentUser = (object) ['id' => 'actor', 'name' => 'Actor'];
    }

    private function notifier(&$recipients)
    {
        $recipients = new FakeRecipients();
        $n = new FakeMentionNotification($recipients);
        $n->records = [
            'Project' => ['p-1' => ['id' => 'p-1', 'shortCode' => 'PRJ']],
            'Issue' => ['i-1' => ['id' => 'i-1', 'issueNumber' => 7]],
            'Conversationroom' => [
                'r-1' => ['id' => 'r-1', 'issueId' => 'i-1'],
                'r-2' => ['id' => 'r-2', 'issueId' => null],
            ],
            'Comment' => ['c-1' => ['id' => 'c-1', 'relatedTo' => 'conversationrooms', 'relatedId' => 'r-1']],
            'Idea' => ['d-1' => ['id' => 'd-1']],
            'Ideamessage' => ['m-1' => ['id' => 'm-1', 'ideaId' => 'd-1']],
        ];

        return $n;
    }

    private function mention($relatedTo, $relatedId, $userId = 'u-1')
    {
        return (object) [
            'userId' => $userId, 'relatedTo' => $relatedTo, 'relatedId' => $relatedId,
            'projectId' => 'p-1', 'createdUser' => 'actor', 'isNotified' => false,
        ];
    }

    public function testIssueMention()
    {
        $n = $this->notifier($r);
        $this->assertNotNull($n->notify($this->mention('issue', 'i-1')));
        $this->assertSame('{{User@actor}} mentioned you in {{Issue@i-1}}', $n->created[0]['description']);
        $this->assertSame('issue', $n->created[0]['relatedTo']);
        $ctx = json_decode($n->created[0]['context'], true);
        $this->assertSame(7, $ctx['issueNumber']);
        $this->assertSame('PRJ', $ctx['projectShortcode']);
        $this->assertSame([['n-1', ['u-1']]], $r->notified);
        $this->assertSame(1, $n->marked);
    }

    public function testCommentMentionPointsAtTheIssue()
    {
        $n = $this->notifier($r);
        $n->notify($this->mention('comment', 'c-1'));
        $this->assertSame('{{User@actor}} mentioned you in {{Issue@i-1}}', $n->created[0]['description']);
        $this->assertSame('c-1', json_decode($n->created[0]['context'], true)['commentId']);
    }

    public function testThreadMention()
    {
        $n = $this->notifier($r);
        $n->notify($this->mention('conversationroom', 'r-1'));
        $this->assertSame('issue', $n->created[0]['relatedTo']);
        $n->notify($this->mention('conversationroom', 'r-2'));
        $this->assertSame('{{User@actor}} mentioned you in {{Conversationroom@r-2}}', $n->created[1]['description']);
    }

    public function testIdeaAndIdeaMessageMentions()
    {
        $n = $this->notifier($r);
        $n->notify($this->mention('idea', 'd-1'));
        $n->notify($this->mention('ideamessage', 'm-1'));
        foreach ($n->created as $data) {
            $this->assertSame('{{User@actor}} mentioned you in {{Idea@d-1}}', $data['description']);
            $this->assertSame('idea', $data['relatedTo']);
        }
        $this->assertSame('m-1', json_decode($n->created[1]['context'], true)['ideamessageId']);
    }

    public function testNothingIsCreatedForAMissingTargetOrSelfMention()
    {
        $n = $this->notifier($r);
        $this->assertNull($n->notify($this->mention('issue', 'missing')));
        $this->assertNull($n->notify($this->mention('issue', 'i-1', 'actor')));
        $this->assertNull($n->notify($this->mention('unknown', 'x')));
        $this->assertSame([], $n->created);
        $this->assertSame(0, $n->marked);
    }
}
