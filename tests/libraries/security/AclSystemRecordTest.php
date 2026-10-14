<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\Acl;
use Gaia\MVC\Models\Permission;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Acl::assertSystemRecordWrite(): writes to system issue
 * statuses and system issue types require setting.update (agaia-3957).
 *
 * Uses the real model metadata and database rows; effective permissions are
 * set directly so no role or user fixtures are needed.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclSystemRecordTest extends TestCase
{
    /**
     * Temporary non-system issue type created for the test.
     */
    private const PROJECT_TYPE_ID = 'test-acl-sysrec-type-0001';

    /**
     * @var string|null
     */
    private static $systemStatusId = null;

    /**
     * @var string|null
     */
    private static $systemTypeId = null;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\Phalcon\Di::class)) {
            return;
        }

        $db = \Phalcon\Di::getDefault()->get('db');
        $status = $db->fetchOne('SELECT id FROM issue_statuses WHERE system = 1 AND deleted = 0 LIMIT 1');
        $type = $db->fetchOne('SELECT id FROM issue_types WHERE system = 1 AND deleted = 0 LIMIT 1');
        self::$systemStatusId = $status ? $status['id'] : null;
        self::$systemTypeId = $type ? $type['id'] : null;

        $db->execute('DELETE FROM issue_types WHERE id = ?', [self::PROJECT_TYPE_ID]);
        $db->execute(
            'INSERT INTO issue_types (id, name, deleted, "createdUser", "modifiedUser", system, "projectId", "createdUserName", "modifiedUserName")
             VALUES (?, ?, 0, ?, ?, 0, ?, ?, ?)',
            [self::PROJECT_TYPE_ID, 'ACL system record test', '1', '1', 'test-acl-sysrec-project', 'Test', 'Test']
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (!class_exists(\Phalcon\Di::class)) {
            return;
        }

        \Phalcon\Di::getDefault()->get('db')->execute('DELETE FROM issue_types WHERE id = ?', [self::PROJECT_TYPE_ID]);
        Permission::clearEffectivePermissions();
    }

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
        if (self::$systemStatusId === null || self::$systemTypeId === null) {
            $this->markTestSkipped('No system issue status/type rows in this database');
        }
    }

    protected function tearDown(): void
    {
        Permission::clearEffectivePermissions();
    }

    public function testUpdatingSystemStatusWithoutSettingUpdateIsDenied()
    {
        $acl = $this->newAcl(0);

        $this->expectException(\Gaia\Exception\Access::class);
        $acl->assertSystemRecordWrite('Issuestatus', ['description' => 'x'], self::$systemStatusId);
    }

    public function testDeletingSystemTypeWithoutSettingUpdateIsDenied()
    {
        $acl = $this->newAcl(0);

        $this->expectException(\Gaia\Exception\Access::class);
        $acl->assertSystemRecordWrite('Issuetype', [], self::$systemTypeId);
    }

    public function testClearingSystemFlagWithoutSettingUpdateIsDenied()
    {
        $acl = $this->newAcl(0);

        $this->expectException(\Gaia\Exception\Access::class);
        $acl->assertSystemRecordWrite('Issuetype', ['system' => 0], self::$systemTypeId);
    }

    /**
     * @dataProvider truthySystemValues
     */
    public function testCreatingSystemRecordWithoutSettingUpdateIsDenied($value)
    {
        $acl = $this->newAcl(0);

        $this->expectException(\Gaia\Exception\Access::class);
        $acl->assertSystemRecordWrite('Issuestatus', ['name' => 'x', 'system' => $value]);
    }

    public function truthySystemValues()
    {
        return [[1], ['1'], [true], ['true']];
    }

    public function testMarkingProjectTypeAsSystemWithoutSettingUpdateIsDenied()
    {
        $acl = $this->newAcl(0);

        $this->expectException(\Gaia\Exception\Access::class);
        $acl->assertSystemRecordWrite('Issuetype', ['system' => 1], self::PROJECT_TYPE_ID);
    }

    public function testSystemWritesAllowedWithSettingUpdate()
    {
        $acl = $this->newAcl(1);

        $acl->assertSystemRecordWrite('Issuestatus', ['description' => 'x'], self::$systemStatusId);
        $acl->assertSystemRecordWrite('Issuetype', [], self::$systemTypeId);
        $acl->assertSystemRecordWrite('Issuestatus', ['name' => 'x', 'system' => 1]);
        $this->addToAssertionCount(1);
    }

    public function testProjectTypeWritesDoNotNeedSettingUpdate()
    {
        $acl = $this->newAcl(0);

        $acl->assertSystemRecordWrite('Issuetype', ['name' => 'x'], self::PROJECT_TYPE_ID);
        $acl->assertSystemRecordWrite('Issuetype', [], self::PROJECT_TYPE_ID);
        $acl->assertSystemRecordWrite('Issuetype', ['name' => 'x', 'system' => 0, 'projectId' => 'p']);
        $acl->assertSystemRecordWrite('Issuetype', ['name' => 'x', 'system' => '0']);
        $this->addToAssertionCount(1);
    }

    public function testModelsWithoutSystemRecordsAreUnaffected()
    {
        $acl = $this->newAcl(0);

        $acl->assertSystemRecordWrite('Issue', ['system' => 1], self::$systemStatusId);
        $this->addToAssertionCount(1);
    }

    /**
     * Acl in permissive mode with a single explicit setting.update row.
     *
     * @param  int $settingUpdate
     * @return Acl
     */
    private function newAcl($settingUpdate)
    {
        $property = new \ReflectionProperty(Permission::class, 'effectivePermissions');
        $property->setAccessible(true);
        $property->setValue(null, [
            'setting.update' => [['allowed' => $settingUpdate, 'roleId' => 'test-role']],
        ]);

        return new Acl(\Phalcon\Di::getDefault(), Acl::RESOLUTION_PERMISSIVE);
    }
}
