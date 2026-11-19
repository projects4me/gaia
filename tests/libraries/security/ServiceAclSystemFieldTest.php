<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\Acl;
use Gaia\Libraries\Security\ServiceAccess;
use Gaia\Libraries\Security\ServiceAcl;
use Gaia\Libraries\Security\TokenScope;
use PHPUnit\Framework\TestCase;

/**
 * A resource opens system fields to one service kind, get and update apart, and may open its rows whatever its
 * authorization groups (D-072). Users and other kinds still never reach a system field (D-071).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceAclSystemFieldTest extends TestCase
{
    /**
     * Metadata of the test resources: Sensor opens its search columns to ares and ignores its group, Probe does
     * not ignore its group, Gadget is closed to services.
     *
     * @var array
     */
    private const META = [
        'Sensor' => [
            'fields' => [
                'id' => ['name' => 'id', 'type' => 'char'],
                'name' => ['name' => 'name', 'type' => 'varchar'],
                'searchText' => ['name' => 'searchText', 'type' => 'text', 'system' => true],
                'searchVector' => ['name' => 'searchVector', 'type' => 'vector', 'system' => true],
                'secret' => ['name' => 'secret', 'type' => 'text', 'system' => true],
            ],
            'acl' => [
                'groups' => ['Gadget'],
                'service' => [
                    'actions' => ['get', 'update'],
                    'kinds' => ['ares'],
                    'filter' => 'Sensor.deleted = 0',
                    'fields' => ['searchVector'],
                    'systemFields' => ['searchText'],
                    'ignoreGroups' => true,
                ],
            ],
        ],
        'Probe' => [
            'fields' => ['id' => ['name' => 'id', 'type' => 'char']],
            'acl' => ['groups' => ['Gadget'], 'service' => ['actions' => ['get'], 'kinds' => ['ares']]],
        ],
        'Gadget' => ['fields' => [], 'acl' => []],
    ];

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    public function testTheOpenedSystemFieldsAreReadOrWrittenByTheListedKind()
    {
        $acl = $this->serviceAcl('ares');

        $this->assertTrue($acl->isFieldActionAllowed('Sensor', 'searchText', 'get'));
        $this->assertTrue($acl->isFieldActionAllowed('Sensor', 'searchVector', 'update'));
        $this->assertSame(
            ['Sensor.name', 'Sensor.searchText'],
            $acl->filterAuthorizedFields('Sensor', ['Sensor.name', 'Sensor.searchText', 'Sensor.searchVector', 'Sensor.secret'])
        );
    }

    public function testAReadOpeningDoesNotOpenTheWriteAndTheOtherWayRound()
    {
        $acl = $this->serviceAcl('ares');

        $this->assertFalse($acl->isFieldActionAllowed('Sensor', 'searchText', 'update'));
        $this->assertFalse($acl->isFieldActionAllowed('Sensor', 'searchVector', 'get'));
        $this->assertFalse($acl->isFieldActionAllowed('Sensor', 'secret', 'get'));
    }

    public function testOtherKindsAndUsersNeverReachTheSystemFields()
    {
        $this->assertFalse($this->serviceAcl('runtime')->isFieldActionAllowed('Sensor', 'searchText', 'get'));

        $user = new class ($this->di()) extends Acl {
            protected function isResourceAllowed($resourceName)
            {
                return true;
            }
        };
        $this->assertFalse($user->isFieldActionAllowed('Sensor', 'searchText', 'get'));
        $this->assertFalse($user->isFieldActionAllowed('Sensor', 'searchVector', 'update'));
    }

    public function testAResourceMayIgnoreItsAuthorizationGroups()
    {
        $acl = $this->serviceAcl('ares');

        $this->assertTrue($acl->isModelActionAllowed('Sensor', 'get'));
        $this->assertFalse($acl->isModelActionAllowed('Probe', 'get'));
    }

    /**
     * @param string $kind
     * @return ServiceAcl
     */
    private function serviceAcl($kind)
    {
        $di = $this->di();
        $acl = new ServiceAcl($di, $kind, new ServiceAccess($di->get('metaManager')), 'svc-self');
        $acl->setTokenScope(new TokenScope('sensor.get sensor.update probe.get'));

        return $acl;
    }

    /**
     * @return \Phalcon\Di\FactoryDefault
     */
    private function di()
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('metaManager', new class (self::META) {
            private $meta;

            public function __construct(array $meta)
            {
                $this->meta = $meta;
            }

            public function getModelMeta($modelName)
            {
                return $this->meta[$modelName] ?? [];
            }

            public function getModelGroups($modelName)
            {
                return $this->meta[$modelName]['acl']['groups'] ?? [];
            }

            public function isSystemField($modelName, $fieldName)
            {
                return !empty($this->meta[$modelName]['fields'][$fieldName]['system']);
            }
        });

        return $di;
    }
}
