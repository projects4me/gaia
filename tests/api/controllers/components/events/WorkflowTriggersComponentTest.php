<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Controller\Components\Events\WorkflowTriggers;

use Gaia\Libraries\Workflows\TriggerDispatcher;
use Gaia\MVC\REST\Controllers\Components\Events\WorkflowTriggersComponent;
use Phalcon\Events\Event;
use PHPUnit\Framework\TestCase;

/**
 * A record named like the Issue model, which is what the component reads.
 */
class Issue
{
}

/**
 * A record of a model that raises no trigger event.
 */
class Milestone
{
}

/**
 * Tests that creates and updates raise their workflow trigger events with
 * the right payload, and that a failing dispatch never fails the write.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowTriggersComponentTest extends TestCase
{
    /**
     * @var array[] Payloads handed to the dispatcher.
     */
    private $dispatched = [];

    protected function setUp(): void
    {
        $GLOBALS['currentUser'] = (object) ['id' => 'user-1', 'name' => 'Ada'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['currentUser']);
    }

    public function testACreateRaisesTheCreateEvent()
    {
        $this->fire('afterCreate', $this->issue(['id' => 'issue-1', 'subject' => 'New']));

        $this->assertSame(['issue.created'], array_column($this->dispatched, 'eventName'));
        $payload = $this->dispatched[0];
        $this->assertSame('project-1', $payload['projectId']);
        $this->assertSame(['type' => 'issue', 'id' => 'issue-1'], $payload['resource']);
        $this->assertSame(['id' => 'user-1', 'name' => 'Ada'], $payload['actor']);
        $this->assertSame('New', $payload['issue']['subject']);
        $this->assertArrayNotHasKey('audit', $payload['issue']);
        $this->assertSame([], $payload['changes']);
    }

    public function testAnUpdateRaisesEachEventWatchingAChangedField()
    {
        $this->fire('afterUpdate', $this->issue([
            'id' => 'issue-1',
            'status' => 'Done',
            'audit' => [
                'status' => ['old' => 'New', 'new' => 'Done'],
                'dateModified' => ['old' => 'a', 'new' => 'b'],
            ],
        ]));

        $this->assertSame(['issue.modified', 'issue.status.changed'], array_column($this->dispatched, 'eventName'));
        $this->assertSame(['status' => ['before' => 'New', 'after' => 'Done']], $this->dispatched[1]['changes']);
    }

    public function testAnUpdateWithoutChangesOrOfAnotherModelRaisesNothing()
    {
        $this->fire('afterUpdate', $this->issue(['id' => 'issue-1', 'audit' => []]));
        $milestone = new Milestone();
        $milestone->id = 'milestone-1';
        $milestone->projectId = 'project-1';
        $this->fire('afterCreate', $milestone);

        $this->assertSame([], $this->dispatched);
    }

    public function testAFailingDispatchDoesNotFailTheWrite()
    {
        $component = new WorkflowTriggersComponent(null, null, new class extends TriggerDispatcher {
            public function __construct()
            {
            }

            public function dispatch(array $payload)
            {
                throw new \RuntimeException('engine down');
            }
        });

        $component->afterCreate(new Event('rest:afterCreate', $this), null, $this->issue(['id' => 'issue-1']));

        $this->addToAssertionCount(1);
    }

    /**
     * @param  array $values
     * @return Issue
     */
    private function issue(array $values)
    {
        $issue = new Issue();
        $issue->projectId = 'project-1';
        foreach ($values as $name => $value) {
            $issue->$name = $value;
        }

        return $issue;
    }

    /**
     * @param  string $hook
     * @param  object $model
     * @return void
     */
    private function fire($hook, $model)
    {
        $dispatched = &$this->dispatched;
        $dispatcher = new class($dispatched) extends TriggerDispatcher {
            private $dispatched;

            public function __construct(array &$dispatched)
            {
                $this->dispatched = &$dispatched;
            }

            public function dispatch(array $payload)
            {
                $this->dispatched[] = $payload;

                return 0;
            }
        };

        (new WorkflowTriggersComponent(null, null, $dispatcher))->$hook(new Event('rest:' . $hook, $this), null, $model);
    }
}
