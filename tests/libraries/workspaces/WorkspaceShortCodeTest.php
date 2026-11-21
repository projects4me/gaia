<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\WorkspaceShortCode;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see WorkspaceShortCode} (Workspaces D-012).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceShortCodeTest extends TestCase
{
    /**
     * Codes are stored trimmed and in lower case.
     *
     * @return void
     */
    public function testNormalizeTrimsAndLowersTheCode()
    {
        $rule = new WorkspaceShortCode();
        $this->assertSame('agaia', $rule->normalize('  AGaia '));
        $this->assertSame('', $rule->normalize(null));
    }

    /**
     * A missing code is required; a malformed or too long one is invalid.
     *
     * @return void
     */
    public function testFormatErrors()
    {
        $rule = new WorkspaceShortCode();
        $this->assertSame(WorkspaceShortCode::REQUIRED, $rule->formatError(''));
        $this->assertSame(WorkspaceShortCode::INVALID, $rule->formatError('1abc'));
        $this->assertSame(WorkspaceShortCode::INVALID, $rule->formatError('a-b'));
        $this->assertSame(WorkspaceShortCode::INVALID, $rule->formatError('a b'));
        $this->assertSame(WorkspaceShortCode::INVALID, $rule->formatError(str_repeat('a', 51)));
    }

    /**
     * Letters followed by letters or digits, up to 50 characters, are well formed.
     *
     * @return void
     */
    public function testWellFormedCodesPass()
    {
        $rule = new WorkspaceShortCode();
        $this->assertNull($rule->formatError('agaia'));
        $this->assertNull($rule->formatError('agaia2'));
        $this->assertNull($rule->formatError(str_repeat('a', 50)));
    }

    /**
     * A new record always sets the code; an update changes it only when the
     * normalized value differs from the stored one.
     *
     * @return void
     */
    public function testIsChange()
    {
        $rule = new WorkspaceShortCode();
        $this->assertTrue($rule->isChange(null, 'abc'));
        $this->assertFalse($rule->isChange(['shortCode' => 'abc'], 'abc'));
        $this->assertFalse($rule->isChange(['shortCode' => 'ABC'], 'abc'));
        $this->assertFalse($rule->isChange(['shortCode' => null], ''));
        $this->assertTrue($rule->isChange(['shortCode' => 'abc'], 'abd'));
        $this->assertTrue($rule->isChange(['shortCode' => null], 'abc'));
    }

    /**
     * A free wanted code is kept as it is.
     *
     * @return void
     */
    public function testFreeCodeFromKeepsAFreeCode()
    {
        $this->assertSame('agaia', $this->ruleWithTaken([])->freeCodeFrom('AGaia'));
    }

    /**
     * A taken code gets the first free number appended.
     *
     * @return void
     */
    public function testFreeCodeFromAppendsTheFirstFreeNumber()
    {
        $this->assertSame('agaia3', $this->ruleWithTaken(['agaia', 'agaia2'])->freeCodeFrom('agaia'));
    }

    /**
     * Characters outside the format and leading digits are dropped; nothing
     * usable falls back to "ws".
     *
     * @return void
     */
    public function testFreeCodeFromCleansTheWantedCode()
    {
        $rule = $this->ruleWithTaken(['ws']);
        $this->assertSame('ab1', $rule->freeCodeFrom('12a-b 1'));
        $this->assertSame('ws2', $rule->freeCodeFrom(''));
        $this->assertSame('ws2', $rule->freeCodeFrom('123'));
    }

    /**
     * A derived code never grows past 50 characters.
     *
     * @return void
     */
    public function testFreeCodeFromStaysWithinTheMaximumLength()
    {
        $long = str_repeat('a', 50);
        $code = $this->ruleWithTaken([$long])->freeCodeFrom($long . 'aaa');

        $this->assertSame(str_repeat('a', 49) . '2', $code);
    }

    /**
     * A rule whose lookup sees only the given codes.
     *
     * @param  array $taken
     * @return WorkspaceShortCode
     */
    private function ruleWithTaken(array $taken)
    {
        return new class ($taken) extends WorkspaceShortCode {
            private $taken;

            public function __construct(array $taken)
            {
                $this->taken = $taken;
            }

            public function isTaken($code, $exceptId = null)
            {
                return in_array($code, $this->taken, true);
            }
        };
    }
}
