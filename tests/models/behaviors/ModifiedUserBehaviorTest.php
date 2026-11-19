<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\modifiedUserBehavior;

/**
 * Unit tests for modifiedUserBehavior: saves made without a logged-in user
 * are attributed to the system user instead of failing.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModifiedUserBehaviorTest extends TestCase
{
    /**
     * @var mixed
     */
    private $savedUser;

    protected function setUp(): void
    {
        global $currentUser;
        $this->savedUser = $currentUser;
    }

    protected function tearDown(): void
    {
        global $currentUser;
        $currentUser = $this->savedUser;
    }

    /**
     * With no global current user the system user is returned.
     *
     * @return void
     */
    public function testNoCurrentUserFallsBackToSystemUser()
    {
        global $currentUser;
        $currentUser = null;

        $user = $this->getCurrentUser();

        $this->assertSame('system', $user->id);
        $this->assertSame('system_user', $user->name);
    }

    /**
     * A logged-in user is returned untouched.
     *
     * @return void
     */
    public function testLoggedInUserIsKept()
    {
        global $currentUser;
        $currentUser = (object) ['id' => 'u-1', 'name' => 'Jane'];

        $user = $this->getCurrentUser();

        $this->assertSame('u-1', $user->id);
        $this->assertSame('Jane', $user->name);
    }

    /**
     * Calls the protected getCurrentUser().
     *
     * @return object
     */
    private function getCurrentUser()
    {
        $method = new \ReflectionMethod(modifiedUserBehavior::class, 'getCurrentUser');
        $method->setAccessible(true);

        return $method->invoke(new modifiedUserBehavior());
    }
}
