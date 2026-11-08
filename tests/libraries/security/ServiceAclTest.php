<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Exception\UnAuthorized;
use Gaia\Libraries\Security\ServiceAccess;
use Gaia\Libraries\Security\ServiceAcl;
use Gaia\Libraries\Security\TokenScope;
use PHPUnit\Framework\TestCase;

/**
 * A service token's Acl is deny by default (D-066): a resource is reachable
 * only when it opted in for the action and kind and the token's scope covers
 * it; no role or permission row is consulted, and every denial is a 401.
 *
 * `Widget` opts in for runtime reads with a record filter, `Gizmo` for
 * runtime reads grouped under `Widget`, `Gadget` does not opt in.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceAclTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    public function testOptedInResourceCoveredByScopeIsAllowed()
    {
        $acl = $this->newAcl('widget.get');

        $acl->authorizeAction('widget.get', null, 'Widget');
        $this->assertTrue($acl->checkAccess('widget.get'));
    }

    public function testResourceNotOptedInIsDeniedWith401EvenUnderFullScope()
    {
        $this->expectException(UnAuthorized::class);
        $this->newAcl('*.*')->authorizeAction('gadget.get', null, 'Gadget');
    }

    public function testOptedInResourceOutsideTheScopeIsDenied()
    {
        $this->expectException(UnAuthorized::class);
        $this->newAcl('gadget.get')->authorizeAction('widget.get', null, 'Widget');
    }

    public function testTokenWithoutPermissionScopesReachesNothing()
    {
        foreach ([null, '', 'application'] as $scope) {
            $this->assertFalse($this->newAcl($scope)->isModelActionAllowed('Widget', 'get'), (string) $scope);
        }
    }

    public function testOtherKindIsDenied()
    {
        $this->assertFalse($this->newAcl('widget.get', 'hermes')->isModelActionAllowed('Widget', 'get'));
    }

    public function testWritesAreDenied()
    {
        $acl = $this->newAcl('widget.*');

        $this->assertFalse($acl->isModelActionAllowed('Widget', 'update'));
        $this->expectException(UnAuthorized::class);
        $acl->checkModelAccess('Widget', 'create');
    }

    public function testAuthorizationGroupMustBeOpenToTheService()
    {
        $this->assertTrue($this->newAcl('gizmo.get widget.get')->isModelActionAllowed('Gizmo', 'get'));
        $this->assertFalse($this->newAcl('gizmo.get', 'runtime', ['Gizmo' => ['Gadget']])
            ->isModelActionAllowed('Gizmo', 'get'));
    }

    public function testIncludedRelationshipsNotOpenToTheServiceAreOmitted()
    {
        $acl = $this->newAcl('widget.get gadget.get gizmo.get');

        $this->assertSame(['gizmos'], array_values($acl->filterAuthorizedRelationships('Widget', ['gizmos', 'gadgets'], 'get')));
    }

    public function testFieldsOfAReadableResourceAreReadable()
    {
        $acl = $this->newAcl('widget.get');

        $this->assertTrue($acl->isFieldActionAllowed('Widget', 'name', 'get'));
        $this->assertFalse($acl->isFieldActionAllowed('Gadget', 'name', 'get'));
        $this->assertFalse($acl->isFieldActionAllowed('Widget', 'name', 'update'));
    }

    public function testRecordFilterLimitsTheRows()
    {
        $params = ['aclWhere' => 'membership'];
        $this->newAcl('widget.get')->applyRecordScope('Widget', $params);
        $this->assertSame('(Widget.shared = 1)', $params['aclWhere']);

        $params = [];
        $this->newAcl('gizmo.get')->applyRecordScope('Gizmo', $params);
        $this->assertArrayNotHasKey('aclWhere', $params);
    }

    public function testRecordOutsideTheFilterIsDenied()
    {
        $acl = $this->newAcl('widget.get');
        $acl->assertRecordIdInScope('Widget', 'shared-widget');
        $acl->assertRecordIdInScope('Gizmo', 'any-gizmo');

        $this->expectException(UnAuthorized::class);
        $acl->assertRecordIdInScope('Widget', 'private-widget');
    }

    /**
     * A ServiceAcl over the test resources.
     *
     * @param  string|null $scope
     * @param  string      $kind
     * @param  array       $groups Authorization groups per model.
     * @return ServiceAcl
     */
    private function newAcl($scope, $kind = 'runtime', array $groups = ['Gizmo' => ['Widget']])
    {
        $access = new ServiceAccess(new class {
            public function getModelMeta($modelName)
            {
                $meta = [
                    'Widget' => ['acl' => ['service' => [
                        'actions' => ['get', 'create', 'update'],
                        'kinds' => ['runtime'],
                        'filter' => 'Widget.shared = 1',
                    ]]],
                    'Gizmo' => ['acl' => ['service' => ['actions' => ['get'], 'kinds' => ['runtime']]]],
                    'Gadget' => ['acl' => []],
                ];

                return $meta[$modelName] ?? [];
            }
        });

        $acl = new class(\Phalcon\Di::getDefault(), $kind, $access, $groups) extends ServiceAcl {
            private $groups;

            public function __construct($di, $kind, $access, array $groups)
            {
                parent::__construct($di, $kind, $access);
                $this->groups = $groups;
            }

            protected function getAuthorizationGroups($modelName)
            {
                return $this->groups[$modelName] ?? [];
            }

            protected function getRelatedModelName($modelAlias, $relName)
            {
                return ['gizmos' => 'Gizmo', 'gadgets' => 'Gadget'][$relName];
            }

            protected function isStructuralField($modelName, $field)
            {
                return false;
            }

            protected function recordMatchesFilter($modelName, $recordId, $filter)
            {
                return $recordId === 'shared-widget';
            }
        };

        $acl->setTokenScope(new TokenScope($scope));
        return $acl;
    }
}
