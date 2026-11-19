<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils\Http;

use PHPUnit\Framework\TestCase;
use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Tests that the validation messages are loaded from the app language files.
 *
 * @author   Agent Odysseus
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RestControllerLanguageTest extends TestCase
{
    private function load(array $codes)
    {
        $class = new \ReflectionClass(RestController::class);
        $controller = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('loadMessages');
        $method->setAccessible(true);
        return $method->invoke($controller, $codes);
    }

    public function testEnglishMessagesAreLoaded()
    {
        $this->assertSame('The e-mail is required', $this->load(['en'])['requiredEmail']);
    }

    public function testRegionalCodeFallsBackToItsBaseLanguage()
    {
        $this->assertArrayHasKey('requiredEmail', $this->load(['en-US']));
    }

    public function testUnknownLanguageFallsBackToEnglish()
    {
        $this->assertArrayHasKey('requiredEmail', $this->load(['xx', 'fr']));
    }

    public function testPathTraversalCodeIsIgnored()
    {
        $this->assertArrayHasKey('requiredEmail', $this->load(['../../config/config']));
    }

    public function testNoCodesStillLoadsEnglish()
    {
        $this->assertArrayHasKey('validEmail', $this->load([]));
    }
}
