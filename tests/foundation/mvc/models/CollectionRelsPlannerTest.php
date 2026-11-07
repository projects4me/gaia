<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\MVC\Models;

use Gaia\Core\MVC\Models\CollectionRelsPlanner;
use PHPUnit\Framework\TestCase;

/**
 * Which collection relationships load in their own query instead of being joined (#4220).
 */
class CollectionRelsPlannerTest extends TestCase
{
    /**
     * Project-like relationship metadata: one hasOne, three hasMany, one hasManyToMany.
     *
     * @var array
     */
    private $meta = [
        'hasOne' => ['manager' => []],
        'hasMany' => ['milestones' => [], 'issuetypes' => [], 'issuestatuses' => []],
        'hasManyToMany' => ['members' => []],
    ];

    /**
     * @var CollectionRelsPlanner
     */
    private $planner;

    protected function setUp(): void
    {
        $this->planner = new CollectionRelsPlanner();
    }

    /**
     * Several collection rels: all but the first load separately, so rows add up instead of multiplying.
     */
    public function testAllButFirstCollectionRelAreSeparated()
    {
        $params = ['rels' => ['members', 'milestones', 'issuetypes', 'issuestatuses']];

        $this->assertSame(
            ['milestones', 'issuetypes', 'issuestatuses'],
            $this->planner->separateRels($params, $this->meta, [])
        );
    }

    /**
     * hasOne rels never multiply rows and stay joined.
     */
    public function testHasOneRelsStayJoined()
    {
        $params = ['rels' => ['manager', 'milestones', 'members']];

        $this->assertSame(['members'], $this->planner->separateRels($params, $this->meta, []));
    }

    /**
     * One collection rel alone is no cross product: nothing changes.
     */
    public function testSingleCollectionRelIsNotSeparated()
    {
        $params = ['rels' => ['manager', 'milestones']];

        $this->assertSame([], $this->planner->separateRels($params, $this->meta, []));
    }

    /**
     * A rel the where clause filters on shapes the base result and stays joined.
     */
    public function testFilteredRelStaysJoined()
    {
        $params = ['rels' => ['members', 'milestones', 'issuetypes']];

        $this->assertSame(
            ['members', 'issuetypes'],
            $this->planner->separateRels($params, $this->meta, ['Project', 'milestones'])
        );
    }

    /**
     * A rel the sort names stays joined.
     */
    public function testSortedRelStaysJoined()
    {
        $params = ['rels' => ['members', 'milestones', 'issuetypes'], 'sort' => 'issuetypes.name'];

        $this->assertSame(['members', 'milestones'], $this->planner->separateRels($params, $this->meta, []));
    }

    /**
     * Requests whose shape depends on the single joined query are left alone.
     *
     * @dataProvider joinedOnlyParamsProvider
     */
    public function testJoinedOnlyParamsKeepOneQuery($param, $value)
    {
        $params = ['rels' => ['members', 'milestones', 'issuetypes'], $param => $value];

        $this->assertSame([], $this->planner->separateRels($params, $this->meta, []));
    }

    public function joinedOnlyParamsProvider()
    {
        return [
            'fields' => ['fields', ['Project.name', 'milestones.name']],
            'addRelFields' => ['addRelFields', true],
            'groupBy' => ['groupBy', 'Project.status'],
            'having' => ['having', 'COUNT(milestones.id) > 1'],
            'count' => ['count', 'Project.id as total'],
        ];
    }

    /**
     * No rels, "none" or a missing rels key: nothing to separate.
     */
    public function testNoRelsRequested()
    {
        $this->assertSame([], $this->planner->separateRels([], $this->meta, []));
        $this->assertSame([], $this->planner->separateRels(['rels' => []], $this->meta, []));
        $this->assertSame([], $this->planner->separateRels(['rels' => ['none']], $this->meta, []));
    }
}
