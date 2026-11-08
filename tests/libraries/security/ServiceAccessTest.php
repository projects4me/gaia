<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\ServiceAccess;
use PHPUnit\Framework\TestCase;

/**
 * Resources open to service tokens only through their `acl.service`
 * metadata (D-066): the action, the service kind, and reads only.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceAccessTest extends TestCase
{
    public function testOptedInResourceAllowsListedActionAndKind()
    {
        $this->assertTrue($this->access()->allows('Widget', 'get', 'runtime'));
    }

    public function testResourceWithoutServiceMetadataIsDenied()
    {
        $this->assertFalse($this->access()->allows('Gadget', 'get', 'runtime'));
    }

    public function testUnknownModelIsDenied()
    {
        $this->assertFalse($this->access()->allows('Nothing', 'get', 'runtime'));
    }

    public function testKindNotListedIsDenied()
    {
        $this->assertFalse($this->access()->allows('Widget', 'get', 'hermes'));
    }

    public function testActionNotListedIsDenied()
    {
        $this->assertFalse($this->access()->allows('Reading', 'get', 'runtime'));
    }

    public function testWritesAreDeniedEvenWhenListed()
    {
        foreach (['create', 'update', 'delete'] as $action) {
            $this->assertFalse($this->access()->allows('Widget', $action, 'runtime'), $action);
        }
    }

    public function testRecordFilterComesFromMetadata()
    {
        $this->assertSame('Widget.shared = 1', $this->access()->recordFilter('Widget'));
        $this->assertNull($this->access()->recordFilter('Reading'));
        $this->assertNull($this->access()->recordFilter('Gadget'));
    }

    /**
     * @return ServiceAccess
     */
    private function access()
    {
        return new ServiceAccess(new class {
            public function getModelMeta($modelName)
            {
                $meta = [
                    'Widget' => ['acl' => ['service' => [
                        'actions' => ['get', 'create', 'update', 'delete'],
                        'kinds' => ['runtime'],
                        'filter' => 'Widget.shared = 1',
                    ]]],
                    'Reading' => ['acl' => ['service' => [
                        'actions' => ['list'],
                        'kinds' => ['runtime'],
                    ]]],
                    'Gadget' => ['acl' => ['group' => 'Gadget']],
                ];
                if (!isset($meta[$modelName])) {
                    throw new \RuntimeException('unknown model');
                }

                return $meta[$modelName];
            }
        });
    }
}
