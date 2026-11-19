<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Exception\BadRequest;
use Gaia\Libraries\Search\SearchRequest;
use PHPUnit\Framework\TestCase;

/**
 * The bounds of a search request (agaia-4666).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchRequestTest extends TestCase
{
    public function testTrimsTheQueryAndDefaultsTheLimit()
    {
        $request = new SearchRequest(array('issue', 'issue'), "  login bug \n");

        $this->assertSame(array('issue'), $request->types());
        $this->assertSame('login bug', $request->query());
        $this->assertSame(SearchRequest::DEFAULT_LIMIT, $request->limit());
    }

    public function testTakesALimitUpToTheMaximum()
    {
        $this->assertSame(50, (new SearchRequest(array('issue'), 'ab', '50'))->limit());
        $this->assertSame(1, (new SearchRequest(array('issue'), 'ab', 1))->limit());
    }

    /**
     * @dataProvider invalidRequests
     */
    public function testRejectsAnOutOfBoundsRequest(array $types, $query, $limit)
    {
        $this->expectException(BadRequest::class);
        new SearchRequest($types, $query, $limit);
    }

    /**
     * @return array
     */
    public function invalidRequests()
    {
        return array(
            'no type' => array(array(), 'login', null),
            'empty type' => array(array(''), 'login', null),
            'short query' => array(array('issue'), ' a ', null),
            'long query' => array(array('issue'), str_repeat('é', 201), null),
            'query not text' => array(array('issue'), array('login'), null),
            'no-break spaces only' => array(array('issue'), "\u{00A0}\u{00A0}\u{00A0}", null),
            'invalid UTF-8' => array(array('issue'), "log\xC3\x28in", null),
            'NUL byte' => array(array('issue'), "log\0in", null),
            'type not a name' => array(array(array('issue')), 'login', null),
            'limit zero' => array(array('issue'), 'login', 0),
            'limit over max' => array(array('issue'), 'login', 51),
            'limit not a number' => array(array('issue'), 'login', '5x'),
        );
    }
}
