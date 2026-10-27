<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\IssueTypeValuePlan;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the issue type migration: which names become values and
 * which values get an en-us label.
 */
class IssueTypeValuePlanTest extends TestCase
{
    public function testNamesBecomeValues()
    {
        $this->assertSame('change_request', IssueTypeValuePlan::valueFor('Change Request'));
        $this->assertSame('sub_task', IssueTypeValuePlan::valueFor('Sub Task'));
        $this->assertSame('bug', IssueTypeValuePlan::valueFor('Bug'));
        $this->assertSame('asdf', IssueTypeValuePlan::valueFor('Asdf'));
        $this->assertSame('d', IssueTypeValuePlan::valueFor('D'));
        $this->assertSame('wont_fix', IssueTypeValuePlan::valueFor("  Won't  Fix "));
        $this->assertSame('stage2', IssueTypeValuePlan::valueFor('Stage-2'));
        $this->assertSame('', IssueTypeValuePlan::valueFor('!!!'));
    }

    public function testValuesAreAtMostThirtyCharacters()
    {
        $this->assertSame(str_repeat('a', 30), IssueTypeValuePlan::valueFor(str_repeat('A', 40)));
    }

    public function testRenamesTypesAndLabelsEachValueOnceFromTheSystemType()
    {
        $plan = new IssueTypeValuePlan([
            $this->type('p1', 'Change request', 0),
            $this->type('s1', 'Change Request', 1),
            $this->type('p2', 'Change Request', 0),
            $this->type('s2', 'Sub Task', 1),
        ], []);

        $this->assertSame(
            ['s1' => 'change_request', 's2' => 'sub_task', 'p1' => 'change_request', 'p2' => 'change_request'],
            $this->sorted($plan->renames(), ['s1', 's2', 'p1', 'p2'])
        );
        $this->assertSame(['change_request' => 'Change Request', 'sub_task' => 'Sub Task'], $plan->labels());
        $this->assertSame([], $plan->skipped());
    }

    public function testRunningAgainChangesNothing()
    {
        $plan = new IssueTypeValuePlan([
            $this->type('s1', 'change_request', 1),
            $this->type('p1', 'change_request', 0),
        ], ['change_request']);

        $this->assertSame([], $plan->renames());
        $this->assertSame([], $plan->labels());
    }

    public function testKeepsLabelsThatAlreadyExist()
    {
        $plan = new IssueTypeValuePlan([$this->type('s1', 'Bug', 1)], ['bug']);

        $this->assertSame(['s1' => 'bug'], $plan->renames());
        $this->assertSame([], $plan->labels());
    }

    public function testDeletedTypesAreRenamedButNotLabelled()
    {
        $plan = new IssueTypeValuePlan([$this->type('p1', 'Old Type', 0, 1)], []);

        $this->assertSame(['p1' => 'old_type'], $plan->renames());
        $this->assertSame([], $plan->labels());
    }

    public function testNamesWithNothingToKeepAreSkipped()
    {
        $plan = new IssueTypeValuePlan([$this->type('p1', '???', 0)], []);

        $this->assertSame([], $plan->renames());
        $this->assertSame([], $plan->labels());
        $this->assertSame(['p1'], $plan->skipped());
    }

    /**
     * @param  string $id
     * @param  string $name
     * @param  int    $system
     * @param  int    $deleted
     * @return array
     */
    private function type($id, $name, $system, $deleted = 0)
    {
        return ['id' => $id, 'name' => $name, 'system' => $system, 'deleted' => $deleted];
    }

    /**
     * @param  array    $map
     * @param  string[] $order
     * @return array
     */
    private function sorted(array $map, array $order)
    {
        $sorted = [];
        foreach ($order as $key) {
            if (array_key_exists($key, $map)) {
                $sorted[$key] = $map[$key];
            }
        }
        return $sorted + $map;
    }
}
