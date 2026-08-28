<?php

namespace Gaia\Tests\Controller\Components\Events;

use PHPUnit\Framework\TestCase;
use Phalcon\Events\Event;
use Gaia\MVC\REST\Controllers\Components\Events\IssueLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\MilestoneLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\ConversationCommentLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\ConversationVoteLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\ConversationLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\NotificationLiveEvents;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;

/**
 * Unit tests for selective Hermes live-event components.
 */
class LiveEventsComponentsTest extends TestCase
{
    public function testIssueCreatePublishesAllowlistedFields()
    {
        $posted = array();
        $component = $this->make(IssueLiveEventsComponent::class, $posted);
        $this->fire($component, 'afterCreate', (object) array(
            'id' => 'issue-1',
            'projectId' => 'project-1',
            'subject' => 'New issue',
            'status' => 'new',
            'assignee' => 'user-1',
            'audit' => array(),
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('issue.created', $posted[0]['eventName']);
        $this->assertEquals('New issue', $posted[0]['changes']['subject']);
        $this->assertArrayNotHasKey('audit', $posted[0]['changes']);
    }

    public function testMultiFieldIssuePatchPublishesEachMatchingEvent()
    {
        $posted = array();
        $component = $this->make(IssueLiveEventsComponent::class, $posted);
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'issue-1',
            'projectId' => 'project-1',
            'issueNumber' => 12,
            'audit' => array(
                'status' => array('old' => 'new', 'new' => 'in_progress'),
                'assignee' => array('old' => 'user-1', 'new' => 'user-2'),
                'startDate' => array('old' => '2026-01-01', 'new' => '2026-01-02'),
                'parentId' => array('old' => null, 'new' => 'issue-2'),
            ),
        ));

        $this->assertEquals(array(
            'issue.status.changed',
            'issue.assignee.changed',
            'issue.dates.changed',
            'issue.dependency.created',
        ), array_column($posted, 'eventName'));
    }

    public function testDependencyReplacementPublishesDeleteThenCreate()
    {
        $posted = array();
        $component = $this->make(IssueLiveEventsComponent::class, $posted);
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'issue-1',
            'projectId' => 'project-1',
            'audit' => array(
                'parentId' => array('old' => 'issue-a', 'new' => 'issue-b'),
            ),
        ));

        $this->assertEquals('issue.dependency.deleted', $posted[0]['eventName']);
        $this->assertNull($posted[0]['changes']['parentId']);
        $this->assertEquals('issue-a', $posted[0]['meta']['predecessorIssueId']);
        $this->assertEquals('issue.dependency.created', $posted[1]['eventName']);
        $this->assertEquals('issue-b', $posted[1]['changes']['parentId']);
    }

    public function testDependencyDeleteUsesOldParent()
    {
        $posted = array();
        $component = $this->make(IssueLiveEventsComponent::class, $posted);
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'issue-1',
            'projectId' => 'project-1',
            'audit' => array(
                'parentId' => array('old' => 'issue-a', 'new' => null),
            ),
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('issue.dependency.deleted', $posted[0]['eventName']);
        $this->assertEquals('issue-a', $posted[0]['meta']['predecessorIssueId']);
    }

    public function testNoOpIssueUpdatePublishesNothing()
    {
        $posted = array();
        $component = $this->make(IssueLiveEventsComponent::class, $posted);
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'issue-1',
            'projectId' => 'project-1',
            'audit' => array(),
        ));
        $this->assertSame(array(), $posted);
    }

    public function testMilestoneCompletedRequiresTransitionIntoCompleted()
    {
        $completed = array();
        $this->fire($this->make(MilestoneLiveEventsComponent::class, $completed), 'afterUpdate', (object) array(
            'id' => 'm1',
            'projectId' => 'project-1',
            'audit' => array(
                'status' => array('old' => 'in_progress', 'new' => 'completed'),
            ),
        ));
        $this->assertEquals('milestone.completed', $completed[0]['eventName']);

        $alreadyCompleted = array();
        $this->fire($this->make(MilestoneLiveEventsComponent::class, $alreadyCompleted), 'afterUpdate', (object) array(
            'id' => 'm1',
            'projectId' => 'project-1',
            'audit' => array(
                'status' => array('old' => 'completed', 'new' => 'completed'),
            ),
        ));
        $this->assertSame(array(), $alreadyCompleted);

        $otherStatus = array();
        $this->fire($this->make(MilestoneLiveEventsComponent::class, $otherStatus), 'afterUpdate', (object) array(
            'id' => 'm1',
            'projectId' => 'project-1',
            'audit' => array(
                'status' => array('old' => 'planned', 'new' => 'in_progress'),
            ),
        ));
        $this->assertSame(array(), $otherStatus);
    }

    public function testCommentEventsIgnoreIssueComments()
    {
        $ignored = array();
        $this->fire($this->make(ConversationCommentLiveEventsComponent::class, $ignored), 'afterCreate', (object) array(
            'id' => 'c1',
            'relatedTo' => 'issue',
            'relatedId' => 'issue-1',
        ));
        $this->assertSame(array(), $ignored);

        $created = array();
        $this->fire($this->make(ConversationCommentLiveEventsComponent::class, $created), 'afterCreate', (object) array(
            'id' => 'c2',
            'relatedTo' => 'conversationrooms',
            'relatedId' => 'room-1',
            'comment' => 'hello',
            'dateCreated' => '2026-08-28 12:34:56',
            'dateModified' => '2026-08-28 12:34:56',
        ));
        $this->assertEquals('conversation.comment.created', $created[0]['eventName']);
        $this->assertEquals('2026-08-28 12:34:56', $created[0]['changes']['dateCreated']);
        $this->assertEquals('2026-08-28 12:34:56', $created[0]['changes']['dateModified']);

        $deleted = array();
        $this->fire($this->make(ConversationCommentLiveEventsComponent::class, $deleted), 'afterDelete', (object) array(
            'id' => 'c2',
            'relatedTo' => 'conversationrooms',
            'relatedId' => 'room-1',
            'comment' => 'hello',
        ));
        $this->assertEquals('conversation.comment.deleted', $deleted[0]['eventName']);
    }

    public function testVoteEventsIgnoreNonConversationVotes()
    {
        $ignored = array();
        $this->fire($this->make(ConversationVoteLiveEventsComponent::class, $ignored), 'afterCreate', (object) array(
            'id' => 'v1',
            'relatedTo' => 'wiki',
            'relatedId' => 'w1',
        ));
        $this->assertSame(array(), $ignored);

        $added = array();
        $this->fire($this->make(ConversationVoteLiveEventsComponent::class, $added), 'afterCreate', (object) array(
            'id' => 'v2',
            'relatedTo' => 'conversationrooms',
            'relatedId' => 'room-1',
            'vote' => 1,
        ));
        $this->assertEquals('conversation.vote.added', $added[0]['eventName']);

        $removed = array();
        $this->fire($this->make(ConversationVoteLiveEventsComponent::class, $removed), 'afterDelete', (object) array(
            'id' => 'v2',
            'relatedTo' => 'conversationrooms',
            'relatedId' => 'room-1',
            'vote' => 1,
        ));
        $this->assertEquals('conversation.vote.removed', $removed[0]['eventName']);
    }

    public function testConversationCreatedOnlyOnCreate()
    {
        $created = array();
        $component = $this->make(ConversationLiveEventsComponent::class, $created);
        $model = (object) array(
            'id' => 'room-1',
            'projectId' => 'project-1',
            'subject' => 'Thread',
        );
        $this->fire($component, 'afterCreate', $model);
        $this->assertEquals('conversation.created', $created[0]['eventName']);

        $updated = array();
        $this->fire($this->make(ConversationLiveEventsComponent::class, $updated), 'afterUpdate', $model);
        $this->assertSame(array(), $updated);
    }

    public function testComponentsPublishThroughTheirOwnHooks()
    {
        $posted = array();
        $publisher = new HermesPublisher(function ($envelope) use (&$posted) {
            $posted[] = $envelope;
        });
        $resolver = $this->createStubResolver('project-1');

        $issue = new IssueLiveEventsComponent($publisher, $resolver);
        $this->fire($issue, 'afterUpdate', (object) array(
            'id' => 'issue-1',
            'projectId' => 'project-1',
            'audit' => array(
                'status' => array('old' => 'new', 'new' => 'done'),
            ),
        ));

        $milestone = new MilestoneLiveEventsComponent($publisher, $resolver);
        $this->fire($milestone, 'afterCreate', (object) array(
            'id' => 'm1',
            'projectId' => 'project-1',
            'name' => 'Sprint',
        ));

        $comment = new ConversationCommentLiveEventsComponent($publisher, $resolver);
        $this->fire($comment, 'afterCreate', (object) array(
            'id' => 'c1',
            'relatedTo' => 'conversationroom',
            'relatedId' => 'room-1',
            'comment' => 'hi',
        ));

        $vote = new ConversationVoteLiveEventsComponent($publisher, $resolver);
        $this->fire($vote, 'afterCreate', (object) array(
            'id' => 'v1',
            'relatedTo' => 'conversationrooms',
            'relatedId' => 'room-1',
            'vote' => true,
        ));

        $conversation = new ConversationLiveEventsComponent($publisher, $resolver);
        $this->fire($conversation, 'afterCreate', (object) array(
            'id' => 'room-1',
            'projectId' => 'project-1',
            'subject' => 'Hello',
        ));

        $this->assertEquals(array(
            'issue.status.changed',
            'milestone.created',
            'conversation.comment.created',
            'conversation.vote.added',
            'conversation.created',
        ), array_column($posted, 'eventName'));
        $this->assertEquals(1, $posted[0]['schemaVersion']);
        $this->assertNotEmpty($posted[0]['eventId']);
        $this->assertEquals('project-1', $posted[0]['projectId']);
    }

    public function testPublisherFailOpenDoesNotThrow()
    {
        $publisher = new HermesPublisher(function () {
            throw new \RuntimeException('hermes down');
        });
        $component = new IssueLiveEventsComponent($publisher, $this->createStubResolver('project-1'));
        $this->fire($component, 'afterCreate', (object) array(
            'id' => 'issue-1',
            'projectId' => 'project-1',
            'subject' => 'x',
        ));
        $this->assertTrue(true);
    }

    public function testPublisherFailOpenOnUnauthorizedDoesNotThrow()
    {
        $request = new \GuzzleHttp\Psr7\Request('POST', 'http://hermes.test/publish');
        $response = new \GuzzleHttp\Psr7\Response(401, array(), '{"error":"unauthorized"}');
        $publisher = new HermesPublisher(function () use ($request, $response) {
            throw new \GuzzleHttp\Exception\ClientException('Unauthorized', $request, $response);
        });

        $envelope = $publisher->publishDomainEvent(
            EventNames::ISSUE_CREATED,
            'project-1',
            'issue',
            'issue-1',
            array('subject' => 'x')
        );

        $this->assertNotNull($envelope);
        $this->assertEquals(EventNames::ISSUE_CREATED, $envelope['eventName']);
    }

    public function testPublisherFailOpenOnTimeoutDoesNotThrow()
    {
        $request = new \GuzzleHttp\Psr7\Request('POST', 'http://hermes.test/publish');
        $publisher = new HermesPublisher(function () use ($request) {
            throw new \GuzzleHttp\Exception\ConnectException(
                'cURL error 28: Operation timed out',
                $request
            );
        });

        $envelope = $publisher->publishDomainEvent(
            EventNames::ISSUE_CREATED,
            'project-1',
            'issue',
            'issue-1',
            array('subject' => 'x')
        );

        $this->assertNotNull($envelope);
        $this->assertEquals(EventNames::ISSUE_CREATED, $envelope['eventName']);
    }

    public function testNotificationCreatedEnvelopeUsesUserScope()
    {
        $posted = array();
        $publisher = new HermesPublisher(function ($envelope) use (&$posted) {
            $posted[] = $envelope;
        });

        $publisher->publishDomainEvent(
            'notification.created',
            'user:user-42',
            'systemnotification',
            'sn-1',
            array('message' => 'You were mentioned'),
            array('recipientId' => 'snr-1', 'recipientUserId' => 'user-42')
        );

        $this->assertCount(1, $posted);
        $this->assertEquals('notification.created', $posted[0]['eventName']);
        $this->assertEquals('user:user-42', $posted[0]['projectId']);
        $this->assertEquals('systemnotification', $posted[0]['resource']['type']);
        $this->assertEquals('sn-1', $posted[0]['resource']['id']);
        $this->assertEquals('You were mentioned', $posted[0]['changes']['message']);
        $this->assertEquals('snr-1', $posted[0]['meta']['recipientId']);
        $this->assertEquals('user-42', $posted[0]['meta']['recipientUserId']);
        $this->assertEquals(1, $posted[0]['schemaVersion']);
    }

    public function testNotificationLiveEventsPicksAllowlistedFields()
    {
        $posted = array();
        $publisher = new HermesPublisher(function ($envelope) use (&$posted) {
            $posted[] = $envelope;
        });
        $events = new NotificationLiveEvents($publisher);

        $events->publishCreated(
            (object) array(
                'id' => 'snr-1',
                'userId' => 'user-42',
            ),
            (object) array(
                'id' => 'sn-2',
                'description' => 'Assigned',
                'context' => '{"issueId":"i1"}',
                'createdUser' => 'user-1',
                'createdUserName' => 'Alice',
                'dateCreated' => '2026-08-20 10:00:00',
                'audit' => array('ignored' => true),
                'metadata' => array(),
            )
        );

        $this->assertCount(1, $posted);
        $this->assertEquals(EventNames::NOTIFICATION_CREATED, $posted[0]['eventName']);
        $this->assertEquals('Assigned', $posted[0]['changes']['description']);
        $this->assertArrayNotHasKey('audit', $posted[0]['changes']);
        $this->assertArrayNotHasKey('metadata', $posted[0]['changes']);
    }

    public function testPublisherRejectsUnknownEventNames()
    {
        $posted = array();
        $publisher = new HermesPublisher(function ($envelope) use (&$posted) {
            $posted[] = $envelope;
        });

        $this->assertNull($publisher->publishDomainEvent(
            'issue.deleted',
            'project-1',
            'issue',
            'issue-1'
        ));
        $this->assertSame(array(), $posted);
    }

    /**
     * @param string $class
     * @param array $posted
     * @return object
     */
    protected function make($class, array &$posted)
    {
        $publisher = new HermesPublisher(function ($envelope) use (&$posted) {
            $posted[] = $envelope;
        });
        return new $class($publisher, $this->createStubResolver('project-1'));
    }

    /**
     * @param object $component
     * @param string $hook
     * @param mixed $model
     * @return void
     */
    protected function fire($component, $hook, $model)
    {
        $component->{$hook}(new Event($hook, $component), null, $model);
    }

    /**
     * @param string $projectId
     * @return object
     */
    protected function createStubResolver($projectId)
    {
        return new class($projectId) {
            private $projectId;
            public function __construct($projectId)
            {
                $this->projectId = $projectId;
            }
            public function resolve($model)
            {
                return isset($model->projectId) && $model->projectId
                    ? $model->projectId
                    : $this->projectId;
            }
        };
    }
}
