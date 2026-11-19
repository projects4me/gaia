<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\RelationshipValidator;
use Gaia\Exception\BadRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RelationshipValidator: a `rels` name the model does not have is a BadRequest naming it
 * (agaia-4735).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RelationshipValidatorTest extends TestCase
{
    /**
     * @var RelationshipValidator
     */
    protected $validator;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }

        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('metaManager', new RelationshipValidatorTestMetaManager(), true);
        $this->validator = new RelationshipValidator($di);
    }

    public function testEmptyRelsPass()
    {
        $this->validator->assertKnown([], 'Issue');
        $this->addToAssertionCount(1);
    }

    public function testKnownRelsPass()
    {
        $this->validator->assertKnown(['project', 'assignedTo'], 'Issue');
        $this->addToAssertionCount(1);
    }

    public function testUnknownRelIsBadRequestNamingIt()
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Unknown relationship "milestone" in rels.');

        $this->validator->assertKnown(['assignedTo', 'milestone', 'project'], 'Issue');
    }

    public function testRelOfAnotherModelIsUnknown()
    {
        $this->expectException(BadRequest::class);

        $this->validator->assertKnown(['project'], 'Upload');
    }
}

/**
 * Meta manager stub: Issue has the relationships project and assignedTo; other models have none.
 */
class RelationshipValidatorTestMetaManager
{
    public function getRelationshipMeta($modelAlias, $relationshipName, $throwException = true)
    {
        $rels = ['Issue' => ['project' => ['relatedModel' => 'Project'], 'assignedTo' => ['relatedModel' => 'User']]];

        return $rels[$modelAlias][$relationshipName] ?? [];
    }
}
