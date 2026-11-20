<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\BearerToken;
use PHPUnit\Framework\TestCase;

/**
 * The bearer token of an Authorization header (agaia-4764), shared by introspection and the pending search list.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BearerTokenTest extends TestCase
{
    public function testItReadsTheTokenOfABearerHeader()
    {
        $this->assertSame('abc123', BearerToken::fromHeader('Bearer abc123'));
        $this->assertSame('abc123', BearerToken::fromHeader("  bearer   abc123\n"));
    }

    /**
     * @dataProvider notBearer
     */
    public function testAnythingElseHasNoToken($header)
    {
        $this->assertNull(BearerToken::fromHeader($header));
    }

    public static function notBearer()
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'basic' => ['Basic dXNlcjpwYXNz'],
            'no token' => ['Bearer'],
            'two words' => ['Bearer abc def'],
        ];
    }
}
