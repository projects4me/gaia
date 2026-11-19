<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\Params;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Params::withDefaults: a model read gets every query parameter it reads, so the query classes stop
 * reading undefined keys and looping over null, without changing the query (agaia-4721).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ParamsTest extends TestCase
{
    public function testAnIdOnlyReadGetsEveryKey()
    {
        $params = Params::withDefaults(['id' => 'abc']);

        $this->assertSame([
            'id' => 'abc',
            'where' => null,
            'sort' => null,
            'order' => null,
            'groupBy' => null,
            'having' => null,
            'fields' => [],
            'rels' => [],
        ], $params);
    }

    public function testGivenValuesAreKept()
    {
        $given = [
            'where' => '((Issue.id : 1))',
            'sort' => 'Issue.subject',
            'order' => 'ASC',
            'groupBy' => [],
            'having' => '',
            'fields' => ['Issue.id'],
            'rels' => ['owner'],
            'limit' => 21,
        ];

        $this->assertSame($given, Params::withDefaults($given));
    }

    public function testNullListsBecomeEmptyArrays()
    {
        $params = Params::withDefaults(['fields' => null, 'rels' => null, 'where' => null]);

        $this->assertSame([], $params['fields']);
        $this->assertSame([], $params['rels']);
        $this->assertNull($params['where']);
    }
}
