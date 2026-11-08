<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Authorization;

use Gaia\Libraries\Authorization\ActingUser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ActingUser: the given user is the current user while the
 * work runs and the previous one comes back, even when the work throws; a
 * failed save is a BadRequest with the model's messages.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActingUserTest extends TestCase
{
    protected function tearDown(): void
    {
        global $currentUser;
        $currentUser = null;
    }

    public function testTheUserIsCurrentWhileTheWorkRunsAndThePreviousOneComesBack()
    {
        global $currentUser;
        $currentUser = (object) ['id' => 'before'];

        $seen = (new ActingUser())->run((object) ['id' => 'admin-1'], function () {
            global $currentUser;
            return $currentUser->id;
        });

        $this->assertSame('admin-1', $seen);
        $this->assertSame('before', $currentUser->id);
    }

    public function testThePreviousUserComesBackWhenTheWorkThrows()
    {
        global $currentUser;
        $currentUser = (object) ['id' => 'before'];

        try {
            (new ActingUser())->run((object) ['id' => 'admin-1'], function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('Expected the exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('before', $currentUser->id);
        }
    }

    public function testNoUserKeepsTheCurrentOne()
    {
        global $currentUser;
        $currentUser = (object) ['id' => 'before'];

        $seen = (new ActingUser())->run(null, function () {
            global $currentUser;
            return $currentUser->id;
        });

        $this->assertSame('before', $seen);
    }

    public function testAFailedSaveIsABadRequestWithTheModelsMessages()
    {
        $model = new class {
            public function save()
            {
                return false;
            }

            public function getMessages()
            {
                return [new \Phalcon\Messages\Message('kind must be one of: runtime', 'kind')];
            }
        };

        $this->expectException(\Gaia\Exception\BadRequest::class);
        $this->expectExceptionMessage('kind must be one of: runtime');

        (new ActingUser())->save($model, null, 'fallback');
    }
}
