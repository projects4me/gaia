<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\ManyToManyDeferral;
use Gaia\Core\MVC\Models\Query\ManyToManyFilter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ManyToManyDeferral: which requested hasManyToMany relationships a list reads after the base query
 * (Tags D-002, agaia-4987).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ManyToManyDeferralTest extends TestCase
{
    /**
     * @var ManyToManyDeferral
     */
    protected $deferral;

    protected function setUp(): void
    {
        $filter = $this->createMock(ManyToManyFilter::class);
        $filter->method('supports')->willReturnCallback(function ($modelAlias, $relName) {
            return in_array($relName, ['tag', 'members'], true);
        });
        $this->deferral = new ManyToManyDeferral($filter);
    }

    public function testSupportedRelationshipsOfAPlainListAreDeferred()
    {
        $this->assertSame(
            ['tag'],
            $this->deferral->deferredRels('Issue', ['rels' => ['project', 'tag', 'assignedUser']])
        );
    }

    public function testGroupedCountedHavingAndStreamedListsDeferNothing()
    {
        $rels = ['rels' => ['tag']];
        $this->assertSame([], $this->deferral->deferredRels('Issue', $rels + ['groupBy' => ['Issue.status']]));
        $this->assertSame([], $this->deferral->deferredRels('Issue', $rels + ['count' => 'Issue.id']));
        $this->assertSame([], $this->deferral->deferredRels('Issue', $rels + ['having' => '(x)']));
        $this->assertSame([], $this->deferral->deferredRels('Issue', $rels, true));
        $this->assertSame([], $this->deferral->deferredRels('Issue', ['rels' => []]));
    }

    public function testARelationshipTheListIsSortedByIsJoined()
    {
        $params = ['rels' => ['tag', 'members']];
        $this->assertSame(['members'], $this->deferral->deferredRels('Issue', $params + ['sort' => 'tag.tag']));
        $this->assertSame(['members'], $this->deferral->deferredRels('Issue', $params + ['sort' => '-tag.tag']));
        $this->assertSame(['members'], $this->deferral->deferredRels('Issue', $params + ['sort' => ['tag.tag DESC']]));
    }

    public function testASortOnAnotherAliasEndingInTheRelNameStillDefers()
    {
        $this->assertSame(
            ['tag'],
            $this->deferral->deferredRels('Issue', ['rels' => ['tag'], 'sort' => 'mytag.tag,Issue.subject'])
        );
    }

    public function testWithoutRelsDropsTheRelationshipsAndTheirFields()
    {
        $params = [
            'rels' => ['project', 'tag'],
            'fields' => ['Issue.id', 'tag.tag', 'project.name', 'subject'],
        ];

        $this->assertSame(
            ['rels' => ['project'], 'fields' => ['Issue.id', 'project.name', 'subject']],
            $this->deferral->withoutRels($params, ['tag'])
        );
        $this->assertSame($params, $this->deferral->withoutRels($params, []));
    }
}
