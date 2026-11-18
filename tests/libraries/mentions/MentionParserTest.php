<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Mentions;

use Gaia\Libraries\Mentions\MentionParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MentionParser: the users a text mentions through links to
 * /app/user/<id>.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MentionParserTest extends TestCase
{
    /**
     * Prometheus' editor anchors, relative and absolute, yield their user ids.
     *
     * @return void
     */
    public function testHtmlAnchors()
    {
        $text = '<p>Hi <a class="badge" href="/app/user/u-1">@Ann</a> and '
            . "<a href='https://prom.projects4.me/app/user/u-2/' class=\"badge\">@Bob</a></p>";

        $this->assertSame(['u-1', 'u-2'], (new MentionParser())->userIds($text));
    }

    /**
     * Markdown links, relative and absolute, with or without a title.
     *
     * @return void
     */
    public function testMarkdownLinks()
    {
        $text = 'Ask [@Ann](/app/user/u-1) or [@Bob](http://localhost:4200/app/user/u-2 "Bob")'
            . ' or [@Cy](</app/user/u-3>).';

        $this->assertSame(['u-1', 'u-2', 'u-3'], (new MentionParser())->userIds($text));
    }

    /**
     * Ids are distinct and in order of first appearance across both forms.
     *
     * @return void
     */
    public function testDistinctInOrder()
    {
        $text = '[@Bob](/app/user/u-2) <a href="/app/user/u-1">@Ann</a> '
            . '<a href="/app/user/u-2">@Bob</a> [@Ann](/app/user/u-1)';

        $this->assertSame(['u-2', 'u-1'], (new MentionParser())->userIds($text));
    }

    /**
     * Links that are not to a user's page, and text without links, mention nobody.
     *
     * @return void
     */
    public function testJunkLinks()
    {
        $parser = new MentionParser();

        $this->assertSame([], $parser->userIds(null));
        $this->assertSame([], $parser->userIds(''));
        $this->assertSame([], $parser->userIds('Plain /app/user/u-1 text and @Ann'));
        $this->assertSame([], $parser->userIds('<a href="/app/project/p-1">x</a>'));
        $this->assertSame([], $parser->userIds('<a href="/app/user/u-1/edit">x</a>'));
        $this->assertSame([], $parser->userIds('<a href="/app/user/">x</a>'));
        $this->assertSame([], $parser->userIds('<a href="app/user/u-1">x</a>'));
        $this->assertSame([], $parser->userIds('<a href="/other/app/user/u-1">x</a>'));
        $this->assertSame([], $parser->userIds('<a href="javascript:/app/user/u-1">x</a>'));
        $this->assertSame([], $parser->userIds('<a href="//evil.example/app/user/u-1">x</a>'));
        $this->assertSame([], $parser->userIds('<a href="/app/user/u-1%27%3E">x</a>'));
        $this->assertSame([], $parser->userIds('[@Ann](/app/users/u-1)'));
        $this->assertSame([], $parser->userIds(['<a href="/app/user/u-1">x</a>']));
    }

    /**
     * A query string or fragment does not hide the user.
     *
     * @return void
     */
    public function testQueryAndFragment()
    {
        $text = '<a href="/app/user/u-1?tab=profile&amp;x=1">@Ann</a> [@Bob](/app/user/u-2#top)';

        $this->assertSame(['u-1', 'u-2'], (new MentionParser())->userIds($text));
    }
}
