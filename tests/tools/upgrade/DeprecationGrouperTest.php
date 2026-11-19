<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\Upgrade;

use PHPUnit\Framework\TestCase;
use Upgrade\DeprecationGrouper;
use Upgrade\MarkdownReport;

/**
 * Unit tests for grouping PHP deprecations and warnings (agaia-4689).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DeprecationGrouperTest extends TestCase
{
    private function lines()
    {
        return [
            "[19-Nov-2026 10:00:00 UTC] PHP Deprecated:  Foo\Bar::__construct(): Implicitly marking parameter \$a as nullable is deprecated in /var/www/html/app/A.php on line 10",
            "PHP Deprecated:  Foo\Baz::run(): Implicitly marking parameter \$b as nullable is deprecated in /var/www/html/app/A.php on line 10",
            "PHP Deprecated:  Foo\Baz::run(): Implicitly marking parameter \$c as nullable is deprecated in /var/www/html/app/B.php on line 20",
            "PHP Warning:  Private methods cannot be final as they are never overridden by other classes in /var/www/html/app/C.php on line 5",
            "PHP Deprecated:  Return type of X::count() should be 4 in /var/www/html/vendor/acme/x/X.php on line 7",
            "PHP Fatal error:  not a deprecation in /var/www/html/app/D.php on line 1",
            "some unrelated line",
        ];
    }

    public function testGroupsByPatternAndSeparatesVendor()
    {
        $g = new DeprecationGrouper();
        $this->assertSame(5, $g->addLines($this->lines()));
        $this->assertSame(['total' => 5, 'gaia' => 4, 'vendor' => 1], $g->totals());

        $gaia = $g->byPattern('gaia');
        $this->assertCount(2, $gaia);
        $this->assertSame('Deprecated', $gaia[0]['level']);
        $this->assertSame(3, $gaia[0]['count']);
        $this->assertSame(
            ['/var/www/html/app/A.php:10' => 2, '/var/www/html/app/B.php:20' => 1],
            $gaia[0]['locations']
        );
        $this->assertSame('Warning', $gaia[1]['level']);
        $this->assertCount(1, $g->byPattern('vendor'));
    }

    public function testPatternDropsNamesQuotesAndNumbers()
    {
        $this->assertSame(
            "Undefined array key 'X' at N",
            DeprecationGrouper::pattern('Undefined array key "id" at 42')
        );
        $this->assertSame('Param $X', DeprecationGrouper::pattern('A\B::f(): Param $name'));
    }

    public function testMarkdownReport()
    {
        $g = new DeprecationGrouper();
        $g->addLines($this->lines());
        $md = (new MarkdownReport())->render($g);
        $this->assertStringContainsString('5 entries: 4 in Gaia code, 1 in vendor.', $md);
        $this->assertStringContainsString('### Deprecated x3:', $md);
        $this->assertStringContainsString('| /var/www/html/app/A.php:10 | 2 |', $md);
    }
}
