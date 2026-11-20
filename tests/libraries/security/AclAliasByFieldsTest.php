<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\Acl;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Acl::getAliasByFields(): only `field AS alias` expressions yield an alias, and a plain
 * field whose name contains "as" (status, lastName) raises no PHP warning or deprecation (agaia-4754).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclAliasByFieldsTest extends TestCase
{
    /**
     * @param array $fields
     * @return array
     */
    private function aliases(array $fields): array
    {
        $acl = (new \ReflectionClass(Acl::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Acl::class, 'getAliasByFields');

        return $method->invoke($acl, ['fields' => $fields]);
    }

    public function testPlainFieldsContainingAsHaveNoAlias(): void
    {
        $this->assertSame([], $this->aliases(['status', 'lastName', 'Issue.statusId']));
    }

    public function testFieldAsAliasIsMapped(): void
    {
        $this->assertSame(
            ['assignee' => 'Issue.assignedTo', 'owner' => 'Issue.owner'],
            $this->aliases(['Issue.assignedTo AS assignee', 'Issue.owner as owner', 'status'])
        );
    }
}
