<?php

namespace Gaia\Tests\Controller\Components\Events;

use PHPUnit\Framework\TestCase;
use Phalcon\Events\Event;
use Gaia\MVC\REST\Controllers\Components\Events\DocLiveEventsComponent;
use Gaia\MVC\REST\Controllers\Components\Events\Support\CollabNotifier;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;
use Gaia\MVC\REST\Controllers\Components\Events\Support\ProjectIdResolver;

/**
 * Unit tests for the doc live events and collab notifications, transports stubbed.
 */
class DocLiveEventsComponentTest extends TestCase
{
    protected $posted = array();
    protected $notified = array();
    protected $previousSecret;

    protected function setUp(): void
    {
        $this->previousSecret = getenv('COLLAB_SECRET');
        putenv('COLLAB_SECRET=test-secret');
    }

    protected function tearDown(): void
    {
        putenv($this->previousSecret === false ? 'COLLAB_SECRET' : 'COLLAB_SECRET=' . $this->previousSecret);
    }

    protected function component($failCollab = false)
    {
        $this->posted = array();
        $this->notified = array();
        $publisher = new HermesPublisher(function ($envelope) {
            $this->posted[] = $envelope;
        });
        $collab = new CollabNotifier(function ($docId, $reason) use ($failCollab) {
            if ($failCollab) {
                throw new \RuntimeException('collab down');
            }
            $this->notified[] = array($docId, $reason);
        });
        return new DocLiveEventsComponent($publisher, new ProjectIdResolver(), $collab);
    }

    protected function fire($component, $hook, $model)
    {
        $component->{$hook}(new Event($hook, $component), null, $model);
    }

    protected function doc(array $audit = array())
    {
        return (object) array('id' => 'doc-1', 'projectId' => 'project-1', 'title' => 'T', 'locked' => false,
            'ownerId' => 'user-1', 'audit' => $audit);
    }

    public function testCreatePublishesDocCreated()
    {
        $this->fire($this->component(), 'afterCreate', $this->doc());
        $this->assertCount(1, $this->posted);
        $this->assertEquals('doc.created', $this->posted[0]['eventName']);
        $this->assertEquals('T', $this->posted[0]['changes']['title']);
        $this->assertEmpty($this->notified);
    }

    public function testRenamePublishesUpdatedWithoutCollabNotice()
    {
        $this->fire($this->component(), 'afterUpdate', $this->doc(array('title' => array('old' => 'A', 'new' => 'B'))));
        $this->assertCount(1, $this->posted);
        $this->assertEquals('doc.updated', $this->posted[0]['eventName']);
        $this->assertEquals(array('title' => 'B'), $this->posted[0]['changes']);
        $this->assertEmpty($this->notified);
    }

    public function testUnchangedSavePublishesNothing()
    {
        $this->fire($this->component(), 'afterUpdate', $this->doc());
        $this->assertEmpty($this->posted);
        $this->assertEmpty($this->notified);
    }

    public function testLockAndUnlockNotifyCollab()
    {
        $component = $this->component();
        $this->fire($component, 'afterUpdate', $this->doc(array('locked' => array('old' => false, 'new' => true))));
        $this->fire($component, 'afterUpdate', $this->doc(array('locked' => array('old' => true, 'new' => false))));
        $this->assertCount(2, $this->posted);
        $this->assertEquals(array(array('doc-1', 'locked'), array('doc-1', 'unlocked')), $this->notified);
    }

    public function testDeletePublishesAndNotifies()
    {
        $this->fire($this->component(), 'afterDelete', $this->doc());
        $this->assertEquals('doc.deleted', $this->posted[0]['eventName']);
        $this->assertEquals(array(array('doc-1', 'deleted')), $this->notified);
    }

    public function testCollabFailureDoesNotThrow()
    {
        $this->fire($this->component(true), 'afterDelete', $this->doc());
        $this->assertCount(1, $this->posted);
        $this->assertEmpty($this->notified);
    }

    public function testNotifierSendsNothingForUnknownReasonOrBlankDoc()
    {
        $sent = array();
        $notifier = new CollabNotifier(function ($d, $r) use (&$sent) {
            $sent[] = $d;
        });
        $this->assertFalse($notifier->notifyAccessChanged('doc-1', 'bogus'));
        $this->assertFalse($notifier->notifyAccessChanged('', 'locked'));
        $this->assertTrue($notifier->notifyAccessChanged('doc-1', 'locked'));
        $this->assertEquals(array('doc-1'), $sent);
    }

    public function testOwnerOnlyChangePublishesWithoutCollabNotice()
    {
        $this->fire($this->component(), 'afterUpdate', $this->doc(array('ownerId' => array('old' => 'u1', 'new' => 'u2'))));
        $this->assertEquals(array('ownerId' => 'u2'), $this->posted[0]['changes']);
        $this->assertEmpty($this->notified);
    }

    public function testLockedIsPublishedAsBoolAndUnchangedLockIsIgnored()
    {
        $component = $this->component();
        $this->fire($component, 'afterUpdate', $this->doc(array('locked' => array('old' => 'f', 'new' => 't'))));
        $this->assertSame(true, $this->posted[0]['changes']['locked']);
        $this->assertEquals(array(array('doc-1', 'locked')), $this->notified);

        $this->posted = array();
        $this->notified = array();
        $this->fire($component, 'afterUpdate', $this->doc(array('locked' => array('old' => 'f', 'new' => false))));
        $this->assertEmpty($this->posted);
        $this->assertEmpty($this->notified);
    }

    public function testNotifierPostsUrlHeaderAndBodyThroughGuzzle()
    {
        $history = array();
        $stack = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler(array(new \GuzzleHttp\Psr7\Response(204))));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $notifier = new CollabNotifier(new \GuzzleHttp\Client(array('handler' => $stack)));

        $this->assertTrue($notifier->notifyAccessChanged('doc 1', 'locked'));
        $request = $history[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertStringEndsWith('/internal/docs/doc%201/access', (string) $request->getUri());
        $this->assertNotEmpty($request->getHeaderLine('X-Collab-Secret'));
        $this->assertEquals(array('reason' => 'locked'), json_decode((string) $request->getBody(), true));
    }

    public function testNotifierReturnsFalseOnServerErrorWithoutLeakingSecret()
    {
        $stack = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler(array(new \GuzzleHttp\Psr7\Response(500))));
        $notifier = new CollabNotifier(new \GuzzleHttp\Client(array('handler' => $stack)));
        $this->assertFalse($notifier->notifyAccessChanged('doc-1', 'deleted'));
    }

    public function testCreatePublishesLockedAsBool()
    {
        foreach (array('t' => true, 'f' => false) as $raw => $expected) {
            $doc = $this->doc();
            $doc->locked = $raw;
            $component = $this->component();
            $this->fire($component, 'afterCreate', $doc);
            $this->assertSame($expected, $this->posted[0]['changes']['locked']);
        }
    }

    public function testNotifierDoesNotFollowRedirects()
    {
        $history = array();
        $stack = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler(array(
            new \GuzzleHttp\Psr7\Response(302, array('Location' => 'http://elsewhere.example/steal')),
        )));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $notifier = new CollabNotifier(new \GuzzleHttp\Client(array('handler' => $stack)));

        $notifier->notifyAccessChanged('doc-1', 'locked');
        $this->assertCount(1, $history);
    }

    public function testNotifierSkipsRequestWhenSecretIsNotConfigured()
    {
        putenv('COLLAB_SECRET');
        $history = array();
        $stack = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler(array(new \GuzzleHttp\Psr7\Response(204))));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $notifier = new CollabNotifier(new \GuzzleHttp\Client(array('handler' => $stack)));

        $this->assertFalse($notifier->notifyAccessChanged('doc-1', 'locked'));
        $this->assertCount(0, $history);
    }
}
