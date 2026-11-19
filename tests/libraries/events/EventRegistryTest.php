<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Events;

use Gaia\Libraries\Events\EventRegistry;
use Gaia\Libraries\Workflows\TriggerEventCatalog;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use PHPUnit\Framework\TestCase;

/**
 * Tests the event registry: its answers on given definitions, and that the
 * real registry file records every event Gaia raises today on the right
 * channels and scopes.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EventRegistryTest extends TestCase
{
    private const USER_SCOPED = [
        EventNames::NOTIFICATION_CREATED,
        EventNames::WORKFLOW_AGENT_RUN_REQUESTED,
        EventNames::USER_ACCESS_CHANGED,
        EventNames::USER_MENTIONED,
    ];

    private function registry()
    {
        return new EventRegistry([
            'thing.created' => [
                'description' => 'Thing created.', 'scopes' => ['project'],
                'channels' => ['hermes', 'workflow'], 'enabled' => true,
            ],
            'thing.watched' => [
                'description' => 'Thing watched.', 'scopes' => ['user'],
                'channels' => ['workflow'], 'enabled' => true,
            ],
            'thing.deleted' => [
                'description' => 'Thing deleted.', 'scopes' => ['project'],
                'channels' => ['hermes'], 'enabled' => false,
            ],
        ]);
    }

    public function testItAnswersFromTheGivenDefinitions()
    {
        $registry = $this->registry();

        $this->assertTrue($registry->has('thing.deleted'));
        $this->assertFalse($registry->has('thing.unknown'));
        $this->assertSame('Thing created.', $registry->definition('thing.created')['description']);
        $this->assertNull($registry->definition('thing.unknown'));
        $this->assertSame(['user'], $registry->scopes('thing.watched'));
        $this->assertSame([], $registry->scopes('thing.unknown'));
        $this->assertNull($registry->serviceKind('thing.watched'));
        $this->assertFalse($registry->planned('thing.created'));
    }

    public function testItKeepsSeveralScopesAndTheServiceKind()
    {
        $registry = new EventRegistry([
            'thing.requested' => ['scopes' => ['user', 'runtimes'], 'channels' => ['hermes'], 'enabled' => true],
            'thing.indexed' => [
                'scopes' => ['service'], 'serviceKind' => 'ares', 'channels' => ['hermes'], 'enabled' => true,
            ],
        ]);

        $this->assertSame(['user', 'runtimes'], $registry->scopes('thing.requested'));
        $this->assertSame(['service'], $registry->scopes('thing.indexed'));
        $this->assertSame('ares', $registry->serviceKind('thing.indexed'));
    }

    public function testAPlannedEventIsOnNoChannel()
    {
        $registry = new EventRegistry([
            'thing.created' => ['scopes' => ['project'], 'channels' => ['hermes', 'workflow'], 'enabled' => true],
            'thing.planned' => [
                'scopes' => ['project'], 'channels' => ['hermes', 'workflow'], 'enabled' => true, 'planned' => true,
            ],
        ]);

        $this->assertTrue($registry->planned('thing.planned'));
        $this->assertTrue($registry->has('thing.planned'));
        $this->assertFalse($registry->publishes('thing.planned', EventRegistry::CHANNEL_HERMES));
        $this->assertSame(['thing.created'], $registry->names(EventRegistry::CHANNEL_HERMES));
        $this->assertSame(['thing.created'], $registry->names(EventRegistry::CHANNEL_WORKFLOW));
    }

    /**
     * @dataProvider invalidDefinitions
     */
    public function testItRejectsAnInvalidDefinition(array $definition, $message)
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        new EventRegistry(['thing.bad' => $definition + ['channels' => ['hermes'], 'enabled' => true]]);
    }

    public function invalidDefinitions()
    {
        return [
            'no scopes' => [[], 'Event thing.bad lists no scopes'],
            'the old single scope' => [['scope' => 'project'], 'Event thing.bad lists no scopes'],
            'unknown scope' => [['scopes' => ['project', 'galaxy']], "Event thing.bad has the unknown scope 'galaxy'"],
            'service without kind' => [['scopes' => ['service']], 'Event thing.bad has the service scope but no serviceKind'],
            'kind without service' => [
                ['scopes' => ['user'], 'serviceKind' => 'ares'], 'Event thing.bad has a serviceKind but no service scope',
            ],
        ];
    }

    public function testADisabledEventIsOnNoChannel()
    {
        $registry = $this->registry();

        $this->assertFalse($registry->enabled('thing.deleted'));
        $this->assertFalse($registry->publishes('thing.deleted', EventRegistry::CHANNEL_HERMES));
        $this->assertSame(['thing.created'], $registry->names(EventRegistry::CHANNEL_HERMES));
    }

    public function testNamesListsTheEnabledEventsOfOneChannel()
    {
        $registry = $this->registry();

        $this->assertSame(['thing.created', 'thing.watched'], $registry->names(EventRegistry::CHANNEL_WORKFLOW));
        $this->assertTrue($registry->publishes('thing.watched', EventRegistry::CHANNEL_WORKFLOW));
        $this->assertFalse($registry->publishes('thing.watched', EventRegistry::CHANNEL_HERMES));
        $this->assertFalse($registry->publishes('thing.unknown', EventRegistry::CHANNEL_HERMES));
    }

    public function testTheFileSendsEveryEventNamesConstantToHermes()
    {
        $hermes = (new EventRegistry())->names(EventRegistry::CHANNEL_HERMES);
        $declared = array_values((new \ReflectionClass(EventNames::class))->getConstants());
        sort($hermes);
        sort($declared);

        $this->assertSame($declared, $hermes);
        $this->assertSame((new EventRegistry())->names(EventRegistry::CHANNEL_HERMES), EventNames::allowed());
        $this->assertCount(26, $declared);
    }

    public function testTheFileRaisesEveryWorkflowTriggerWithTheCatalogsSource()
    {
        $registry = new EventRegistry();
        $catalog = new TriggerEventCatalog();
        $workflow = $registry->names(EventRegistry::CHANNEL_WORKFLOW);
        $triggers = $catalog->names();
        sort($workflow);
        sort($triggers);

        $this->assertSame($triggers, $workflow);
        foreach ($catalog->names() as $name) {
            $this->assertSame($catalog->definition($name), $registry->definition($name)['source'], $name);
        }
    }

    public function testTheFileRecordsOnlyHermesAndWorkflowEvents()
    {
        $registry = new EventRegistry();
        $everyName = array_unique(array_merge(
            $registry->names(EventRegistry::CHANNEL_HERMES),
            $registry->names(EventRegistry::CHANNEL_WORKFLOW)
        ));

        $this->assertCount(31, $everyName);
        foreach ($everyName as $name) {
            $definition = $registry->definition($name);
            $this->assertNotEmpty($definition['description'], $name);
            $this->assertIsArray($definition['consumers'], $name);
            $expected = in_array($name, self::USER_SCOPED, true) ? ['user'] : ['project'];
            if ($name === EventNames::WORKFLOW_AGENT_RUN_REQUESTED) {
                $expected = ['user', 'runtimes'];
            }
            if ($name === EventNames::SEARCH_SEMANTIC_CHANGED) {
                $expected = ['service'];
            }
            $this->assertSame($expected, $registry->scopes($name), $name);
            $this->assertFalse($registry->planned($name), $name);
        }
    }

    public function testTheFilePlansTheDocEventsAndRaisesTheSemanticSearchEvent()
    {
        $registry = new EventRegistry();
        $hermes = $registry->names(EventRegistry::CHANNEL_HERMES);

        foreach (['doc.created', 'doc.updated', 'doc.deleted'] as $name) {
            $this->assertTrue($registry->planned($name), $name);
            $this->assertSame(['project'], $registry->scopes($name), $name);
            $this->assertNotContains($name, $hermes);
        }
        $this->assertFalse($registry->planned('search.semantic.changed'));
        $this->assertSame(['service'], $registry->scopes('search.semantic.changed'));
        $this->assertSame('ares', $registry->serviceKind('search.semantic.changed'));
        $this->assertContains('search.semantic.changed', $hermes);
        $this->assertNull($registry->serviceKind(EventNames::ISSUE_CREATED));
    }

    public function testOnlyTheWorkflowEventsHermesAlsoKnowsAreOnBothChannels()
    {
        $registry = new EventRegistry();
        $both = array_values(array_intersect(
            $registry->names(EventRegistry::CHANNEL_HERMES),
            $registry->names(EventRegistry::CHANNEL_WORKFLOW)
        ));
        sort($both);

        $this->assertSame([
            EventNames::ISSUE_ASSIGNEE_CHANGED,
            EventNames::ISSUE_CREATED,
            EventNames::ISSUE_STATUS_CHANGED,
            EventNames::MILESTONE_COMPLETED,
        ], $both);
    }
}
