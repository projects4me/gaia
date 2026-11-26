<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Tags;

use Gaia\Libraries\Tags\TagName;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the tag name rules.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagNameTest extends TestCase
{
    public function testNormaliseTrimsAndCollapsesWhitespaceButKeepsCase()
    {
        $this->assertSame('Backend', TagName::normalise('Backend '));
        $this->assertSame('Needs Review', TagName::normalise("\n Needs \t\t Review  "));
        $this->assertSame('', TagName::normalise(null));
    }

    public function testErrorAllowsOneToFiftyCharacters()
    {
        $this->assertNull(TagName::error('a'));
        $this->assertNull(TagName::error(str_repeat('é', 50)));
        $this->assertSame(TagName::EMPTY_NAME, TagName::error(''));
        $this->assertSame(TagName::TOO_LONG, TagName::error(str_repeat('a', 51)));
    }
}
