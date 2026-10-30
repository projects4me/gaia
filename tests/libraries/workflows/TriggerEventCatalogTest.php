<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\ModelFieldTypes;
use Gaia\Libraries\Workflows\NodeConfigRules;
use Gaia\Libraries\Workflows\NodeKindCatalog;
use Gaia\Libraries\Workflows\TriggerEventCatalog;
use Gaia\Libraries\Workflows\TriggerEventDescriber;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use PHPUnit\Framework\TestCase;

/**
 * Tests the trigger event catalog: which writes raise which events, that the
 * node kinds offer exactly its triggers, and the editor's description of
 * them, read from the real model metadata.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TriggerEventCatalogTest extends TestCase
{
    public function testACreateRaisesTheModelsCreateEvents()
    {
        $catalog = new TriggerEventCatalog();

        $this->assertSame([EventNames::ISSUE_CREATED], $catalog->raisedBy('Issue', TriggerEventCatalog::ON_CREATE));
        $this->assertSame(['comment.added'], $catalog->raisedBy('Comment', TriggerEventCatalog::ON_CREATE));
        $this->assertSame(['time.logged'], $catalog->raisedBy('Timelog', TriggerEventCatalog::ON_CREATE));
        $this->assertSame([], $catalog->raisedBy('Milestone', TriggerEventCatalog::ON_CREATE));
    }

    public function testAnUpdateRaisesTheEventsWatchingAChangedField()
    {
        $catalog = new TriggerEventCatalog();

        $this->assertSame(
            ['issue.modified', 'issue.assignee.changed'],
            $catalog->raisedBy('Issue', TriggerEventCatalog::ON_UPDATE, ['assignee', 'dateModified'])
        );
        $this->assertSame(
            ['issue.modified', 'issue.status.changed'],
            $catalog->raisedBy('Issue', TriggerEventCatalog::ON_UPDATE, ['statusId'])
        );
        $this->assertSame(['issue.modified'], $catalog->raisedBy('Issue', TriggerEventCatalog::ON_UPDATE, ['subject']));
        $this->assertSame(['wiki.modified'], $catalog->raisedBy('Wiki', TriggerEventCatalog::ON_UPDATE, ['markUp']));
    }

    public function testBookkeepingAloneIsNoModification()
    {
        $this->assertSame([], (new TriggerEventCatalog())->raisedBy(
            'Issue',
            TriggerEventCatalog::ON_UPDATE,
            ['dateModified', 'modifiedUser', 'modifiedUserName', 'lastActivityDate']
        ));
    }

    public function testSharedEventsKeepTheirLiveEventNames()
    {
        $names = (new TriggerEventCatalog())->names();

        $this->assertContains(EventNames::ISSUE_CREATED, $names);
        $this->assertContains(EventNames::ISSUE_STATUS_CHANGED, $names);
        $this->assertContains(EventNames::ISSUE_ASSIGNEE_CHANGED, $names);
    }

    public function testTriggerKindsAreTheCatalogEventsAndTheSchedule()
    {
        $kinds = new NodeKindCatalog();
        $expected = array_merge((new TriggerEventCatalog())->names(), [TriggerEventCatalog::SCHEDULE]);

        foreach ($expected as $name) {
            $this->assertTrue($kinds->has(NodeKindCatalog::TRIGGER, $name), $name);
            $this->assertSame(['next'], $kinds->ports(NodeKindCatalog::TRIGGER, $name, []));
        }
        $this->assertFalse($kinds->has(NodeKindCatalog::TRIGGER, 'milestone.created'));
        $this->assertSame(
            ['cron' => ['type' => 'cron', 'required' => true]],
            $kinds->configFields(NodeKindCatalog::TRIGGER, TriggerEventCatalog::SCHEDULE)
        );
        $this->assertSame(['condition', 'actor', 'action'], array_slice($kinds->categories(), 1));
    }

    public function testAScheduleNeedsAValidCron()
    {
        $rules = new NodeConfigRules();

        $this->assertSame([], $rules->errors('trigger', 'schedule', ['cron' => '0 9 * * 1-5']));
        $this->assertSame(
            ["config.cron has a hour out of range 0-23 in '25'."],
            $rules->errors('trigger', 'schedule', ['cron' => '0 25 * * *'])
        );
        $this->assertSame(['config.cron is required for schedule.'], $rules->errors('trigger', 'schedule', []));
    }

    public function testFieldTypesComeFromTheModelMetadata()
    {
        $types = (new ModelFieldTypes())->forModel('Issue');

        $this->assertSame('string', $types['subject']);
        $this->assertSame('number', $types['issueNumber']);
        $this->assertSame('datetime', $types['dateCreated']);
        $this->assertArrayNotHasKey('deleted', $types);
        $this->assertSame([], (new ModelFieldTypes())->forModel('../Issue'));
    }

    public function testDescribesEachTriggerForTheEditor()
    {
        $triggers = [];
        foreach ((new TriggerEventDescriber())->all() as $trigger) {
            $triggers[$trigger['name']] = $trigger;
        }

        $this->assertSame(
            array_merge((new TriggerEventCatalog())->names(), [TriggerEventCatalog::SCHEDULE]),
            array_keys($triggers)
        );

        $assignee = $triggers['issue.assignee.changed'];
        $this->assertSame('Issue', $assignee['module']);
        $this->assertSame(['assignee'], $assignee['watchedFields']);
        $paths = array_column($assignee['fields'], 'type', 'path');
        $this->assertSame('string', $paths['issue.subject']);
        $this->assertSame('string', $paths['changes.assignee.before']);
        $this->assertSame('string', $paths['actor.name']);
        $this->assertArrayNotHasKey('changes.subject.after', $paths);

        $created = array_column($triggers['issue.created']['fields'], 'type', 'path');
        $this->assertArrayNotHasKey('changes.subject.after', $created);

        $modified = array_column($triggers['issue.modified']['fields'], 'type', 'path');
        $this->assertSame('string', $modified['changes.subject.after']);
        $this->assertArrayNotHasKey('changes.dateModified.after', $modified);

        $this->assertNull($triggers['schedule']['module']);
        $this->assertSame(
            ['occurredAt', 'schedule.cron', 'schedule.firedAt'],
            array_column($triggers['schedule']['fields'], 'path')
        );
    }
}
