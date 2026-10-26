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
use Gaia\MVC\REST\Controllers\Components\Events\IdeaLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\IdeamessageLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\IdeaplanLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\IdeaplanissueLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;
use Gaia\MVC\REST\Controllers\Components\Events\Support\ProjectIdResolver;

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

    public function testCommentUpdatePublishesTheChangedFields()
    {
        $posted = array();
        $this->fire($this->make(ConversationCommentLiveEventsComponent::class, $posted), 'afterUpdate', (object) array(
            'id' => 'c2',
            'relatedTo' => 'conversationrooms',
            'relatedId' => 'room-1',
            'audit' => array(
                'comment' => array('old' => 'hello', 'new' => 'hello again'),
            ),
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('conversation.comment.updated', $posted[0]['eventName']);
        $this->assertEquals('c2', $posted[0]['resource']['id']);
        $this->assertEquals(array('comment' => 'hello again'), $posted[0]['changes']);
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

    public function testNotificationLiveEventsIncludesRelatedToAndRelatedIdWhenSet()
    {
        $posted = array();
        $publisher = new HermesPublisher(function ($envelope) use (&$posted) {
            $posted[] = $envelope;
        });
        $events = new NotificationLiveEvents($publisher);

        $events->publishCreated(
            (object) array('id' => 'snr-1', 'userId' => 'user-42'),
            (object) array(
                'id' => 'sn-3',
                'description' => 'Assigned',
                'relatedTo' => 'issue',
                'relatedId' => 'i1',
            )
        );
        $events->publishCreated(
            (object) array('id' => 'snr-2', 'userId' => 'user-42'),
            (object) array(
                'id' => 'sn-4',
                'description' => 'Legacy',
                'relatedTo' => null,
                'relatedId' => null,
            )
        );

        $this->assertCount(2, $posted);
        $this->assertEquals('issue', $posted[0]['changes']['relatedTo']);
        $this->assertEquals('i1', $posted[0]['changes']['relatedId']);
        $this->assertArrayNotHasKey('relatedTo', $posted[1]['changes']);
        $this->assertArrayNotHasKey('relatedId', $posted[1]['changes']);
    }

    public function testIdeaCreatePublishesTitleAndStatus()
    {
        $posted = array();
        $this->fire($this->make(IdeaLiveEventsComponent::class, $posted), 'afterCreate', (object) array(
            'id' => 'idea-1',
            'projectId' => 'project-9',
            'title' => 'Faster boards',
            'description' => 'Long text',
            'status' => 'draft',
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('idea.created', $posted[0]['eventName']);
        $this->assertEquals('project-9', $posted[0]['projectId']);
        $this->assertEquals('idea', $posted[0]['resource']['type']);
        $this->assertEquals('idea-1', $posted[0]['resource']['id']);
        $this->assertEquals(array('title' => 'Faster boards', 'status' => 'draft'), $posted[0]['changes']);
        $this->assertEquals(array('ideaId' => 'idea-1'), $this->metaWithoutActor($posted[0]));
    }

    public function testIdeaUpdatePublishesOnlyChangedTrackedFields()
    {
        $posted = array();
        $component = $this->make(IdeaLiveEventsComponent::class, $posted);
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'idea-1',
            'projectId' => 'project-1',
            'audit' => array(
                'status' => array('old' => 'draft', 'new' => 'planning'),
                'modifiedUser' => array('old' => 'u1', 'new' => 'u2'),
            ),
        ));
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'idea-1',
            'projectId' => 'project-1',
            'audit' => array(
                'modifiedUser' => array('old' => 'u1', 'new' => 'u2'),
            ),
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('idea.updated', $posted[0]['eventName']);
        $this->assertEquals(array('status' => 'planning'), $posted[0]['changes']);
        $this->assertEquals('idea-1', $posted[0]['meta']['ideaId']);
    }

    public function testIdeaMessageCreatePublishesKindWithoutBody()
    {
        $posted = array();
        $this->fire($this->make(IdeamessageLiveEventsComponent::class, $posted), 'afterCreate', (object) array(
            'id' => 'msg-1',
            'ideaId' => 'idea-1',
            'body' => 'secret text',
            'kind' => 'user',
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('idea.message.created', $posted[0]['eventName']);
        $this->assertEquals('ideamessage', $posted[0]['resource']['type']);
        $this->assertEquals(array('kind' => 'user'), $posted[0]['changes']);
        $this->assertEquals(array('ideaId' => 'idea-1'), $this->metaWithoutActor($posted[0]));
    }

    public function testIdeaPlanCreatePublishesVersionAndStatusWithoutBody()
    {
        $posted = array();
        $this->fire($this->make(IdeaplanLiveEventsComponent::class, $posted), 'afterCreate', (object) array(
            'id' => 'plan-1',
            'ideaId' => 'idea-1',
            'version' => 2,
            'body' => 'plan text',
            'status' => 'draft',
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('idea.plan.created', $posted[0]['eventName']);
        $this->assertEquals('ideaplan', $posted[0]['resource']['type']);
        $this->assertEquals(array('version' => 2, 'status' => 'draft'), $posted[0]['changes']);
        $this->assertEquals(array('ideaId' => 'idea-1', 'version' => 2), $this->metaWithoutActor($posted[0]));
    }

    public function testIdeaPlanStatusChangedOnlyWhenStatusOrErrorChanged()
    {
        $posted = array();
        $component = $this->make(IdeaplanLiveEventsComponent::class, $posted);
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'plan-1',
            'ideaId' => 'idea-1',
            'version' => 2,
            'status' => 'failed',
            'error' => 'Ares timeout',
            'audit' => array(
                'error' => array('old' => null, 'new' => 'Ares timeout'),
            ),
        ));
        $this->fire($component, 'afterUpdate', (object) array(
            'id' => 'plan-1',
            'ideaId' => 'idea-1',
            'version' => 2,
            'status' => 'failed',
            'audit' => array(
                'body' => array('old' => 'a', 'new' => 'b'),
            ),
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('idea.plan.status.changed', $posted[0]['eventName']);
        $this->assertEquals(array('status' => 'failed', 'error' => 'Ares timeout'), $posted[0]['changes']);
        $this->assertEquals(array('ideaId' => 'idea-1', 'version' => 2), $this->metaWithoutActor($posted[0]));
    }

    public function testIdeaPlanIssueCreatePublishesKeyAndIssueId()
    {
        $posted = array();
        $this->fire($this->make(IdeaplanissueLiveEventsComponent::class, $posted), 'afterCreate', (object) array(
            'id' => 'pi-1',
            'ideaId' => 'idea-1',
            'ideaplanId' => 'plan-1',
            'key' => 'T1',
            'issueId' => 'issue-7',
        ));

        $this->assertCount(1, $posted);
        $this->assertEquals('idea.planissue.created', $posted[0]['eventName']);
        $this->assertEquals('ideaplanissue', $posted[0]['resource']['type']);
        $this->assertEquals(array('key' => 'T1', 'issueId' => 'issue-7'), $posted[0]['changes']);
        $this->assertEquals(array('ideaId' => 'idea-1', 'ideaplanId' => 'plan-1'), $this->metaWithoutActor($posted[0]));
    }

    public function testResolverResolvesIdeaChildrenThroughTheIdea()
    {
        $resolver = new class extends ProjectIdResolver {
            public $lookups = array();
            protected function findById($shortName, $id)
            {
                $this->lookups[] = array($shortName, $id);
                return (object) array('id' => $id, 'projectId' => 'project-idea');
            }
        };

        $this->assertEquals('project-idea', $resolver->resolve((object) array('ideaId' => 'idea-1')));
        $this->assertEquals(array(array('Idea', 'idea-1')), $resolver->lookups);

        $resolver->lookups = array();
        $resolver->resolve((object) array('ideaId' => 'idea-1', 'issueId' => 'issue-1'));
        $this->assertEquals(array(array('Idea', 'idea-1')), $resolver->lookups);

        $resolver->lookups = array();
        $resolver->resolve((object) array('issueId' => 'issue-1'));
        $this->assertEquals(array(array('Issue', 'issue-1')), $resolver->lookups);
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
     * @param array $envelope
     * @return array
     */
    protected function metaWithoutActor(array $envelope)
    {
        return array_diff_key($envelope['meta'], array('actorName' => true));
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
