<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Tools;

use Gaia\Libraries\Tools\ToolCatalogSync;
use Gaia\MVC\Models\Tool;
use Gaia\MVC\Models\User;
use Gaia\Tests\Libraries\Security\AgentTestFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a sync makes a server's tools rows match the listed tools by
 * name: adds, updates, soft-deletes and restores, keeping tool ids.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolCatalogSyncTest extends TestCase
{
    /**
     * User the rows are written as.
     */
    private const USER_ID = 'test-user-tool-catalog-sync';

    /**
     * Server id the rows belong to; no tool_servers row is needed.
     */
    private const SERVER_ID = 'test-toolserver-catalog-sync';

    public static function setUpBeforeClass(): void
    {
        AgentTestFixtures::ensureHumanUser(self::USER_ID, 'tool-catalog-sync@gaia.test');
    }

    public static function tearDownAfterClass(): void
    {
        self::purge();
        AgentTestFixtures::deleteUser(self::USER_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = User::findFirstById(self::USER_ID);
        self::purge();
    }

    public function testAddsUpdatesAndRemovesByName()
    {
        $sync = new ToolCatalogSync();
        $counts = $sync->sync(self::SERVER_ID, [
            $this->listed('echo', 'Echo v1'),
            $this->listed('sum', 'Sum'),
        ]);
        $this->assertSame(['added' => 2, 'updated' => 0, 'removed' => 0], $counts);
        $echoId = $this->tool('echo')->id;

        $counts = $sync->sync(self::SERVER_ID, [
            $this->listed('echo', 'Echo v2'),
            $this->listed('time', 'Time'),
        ]);

        $this->assertSame(['added' => 1, 'updated' => 1, 'removed' => 1], $counts);
        $echo = $this->tool('echo');
        $this->assertSame($echoId, $echo->id);
        $this->assertSame('Echo v2', $echo->description);
        $this->assertTrue(filter_var($this->tool('sum')->deleted, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var($this->tool('time')->deleted, FILTER_VALIDATE_BOOLEAN));
    }

    public function testRestoresARemovedToolWithItsId()
    {
        $sync = new ToolCatalogSync();
        $sync->sync(self::SERVER_ID, [$this->listed('echo', 'Echo')]);
        $echoId = $this->tool('echo')->id;
        $sync->sync(self::SERVER_ID, []);

        $counts = $sync->sync(self::SERVER_ID, [$this->listed('echo', 'Echo')]);

        $this->assertSame(['added' => 0, 'updated' => 1, 'removed' => 0], $counts);
        $echo = $this->tool('echo');
        $this->assertSame($echoId, $echo->id);
        $this->assertFalse(filter_var($echo->deleted, FILTER_VALIDATE_BOOLEAN));
    }

    public function testAnUnchangedListWritesNothing()
    {
        $sync = new ToolCatalogSync();
        $sync->sync(self::SERVER_ID, [$this->listed('echo', 'Echo')]);

        $this->assertSame(
            ['added' => 0, 'updated' => 0, 'removed' => 0],
            $sync->sync(self::SERVER_ID, [$this->listed('echo', 'Echo')])
        );
    }

    /**
     * @param  string $name
     * @param  string $description
     * @return array
     */
    private function listed($name, $description)
    {
        return ['name' => $name, 'description' => $description, 'inputSchema' => '{"type":"object"}'];
    }

    /**
     * The stored row of the test server with this name, deleted or not.
     *
     * @param  string $name
     * @return Tool
     */
    private function tool($name)
    {
        return Tool::findFirst([
            'conditions' => 'toolServerId = :server: AND name = :name:',
            'bind' => ['server' => self::SERVER_ID, 'name' => $name],
        ]);
    }

    private static function purge()
    {
        \Phalcon\Di::getDefault()->getShared('db')->execute(
            'DELETE FROM tools WHERE "toolServerId" = ?',
            [self::SERVER_ID]
        );
    }
}
