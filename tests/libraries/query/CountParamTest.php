<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\CountParam;
use Gaia\Exception\BadRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CountParam: the `count` list parameter becomes a valid COUNT column or a 400 (agaia-4735).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CountParamTest extends TestCase
{
    public function testAFieldAloneGetsTheDefaultAlias()
    {
        $this->assertSame('COUNT(Issue.id) AS total', CountParam::parse('Issue.id')->toColumn());
    }

    public function testAnAliasIsKeptWhateverTheCaseOfAs()
    {
        $this->assertSame('COUNT(Issue.id) AS issues', CountParam::parse('Issue.id as issues')->toColumn());
        $this->assertSame('COUNT(id) AS issues', CountParam::parse(' id AS issues ')->toColumn());
    }

    public function testAFieldContainingAsIsNotSplit()
    {
        $param = CountParam::parse('Issue.lastActivityDate');

        $this->assertSame('Issue.lastActivityDate', $param->getField());
        $this->assertSame('total', $param->getAlias());
    }

    public function testAGroupWithTheIdIsAccepted()
    {
        $param = CountParam::parse('Issue.id');

        $param->assertGroupedBy('Issue.priority, Issue.id', 'Issue');
        $param->assertGroupedBy('id', 'Issue');
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider groupsWithoutTheId
     */
    public function testAGroupWithoutTheIdIsABadRequest($groupBy)
    {
        $this->expectException(BadRequest::class);

        CountParam::parse('Issue.id')->assertGroupedBy($groupBy, 'Issue');
    }

    public function groupsWithoutTheId()
    {
        return [
            'no group' => [null],
            'other field' => ['Issue.priority'],
            'another model id' => ['Project.id'],
        ];
    }

    /**
     * @dataProvider invalidValues
     */
    public function testAnythingButAFieldAndAliasIsABadRequest($raw)
    {
        $this->expectException(BadRequest::class);

        CountParam::parse($raw);
    }

    public function invalidValues()
    {
        return [
            'phql after the field' => ['Issue.id) FROM Issue'],
            'missing alias' => ['Issue.id as'],
            'two aliases' => ['Issue.id as a as b'],
            'function call' => ['COUNT(Issue.id)'],
            'array' => [['Issue.id']],
        ];
    }
}
