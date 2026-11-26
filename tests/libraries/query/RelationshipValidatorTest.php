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

    public function testQueryOnARelationNotInRelsIsBadRequestNamingIt()
    {
        // The Gantt's milestone search: Gaia joins only what rels names, so the filter would reach an unjoined
        // alias and fail with a 500 (agaia-4985).
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage(
            'Invalid query: relationship "project" is used in the query but not requested in rels.'
        );

        $this->validator->assertQueriedAreRequested(
            '((Issue.projectId : p-1) AND (assignedTo.name : Ann)) AND ((project.name : Gaia))',
            ['assignedTo'],
            'Issue'
        );
    }

    public function testQueryOnRequestedRelationsPasses()
    {
        $this->validator->assertQueriedAreRequested(
            "((Issue.subject : 'a (project.name : x)') AND (project.name : Gaia) AND (assignedTo.name : Ann))",
            ['project', 'assignedTo'],
            'Issue'
        );
        $this->addToAssertionCount(1);
    }

    public function testNoRelsJoinsEveryRelationSoAnyQueryPasses()
    {
        $this->validator->assertQueriedAreRequested('((project.name : Gaia))', [], 'Issue');
        $this->addToAssertionCount(1);
    }

    public function testQueryWithoutRelationsAndEmptyQueryPass()
    {
        $this->validator->assertQueriedAreRequested('((Issue.subject : x) OR (status : open))', ['project'], 'Issue');
        $this->validator->assertQueriedAreRequested('', ['project'], 'Issue');
        $this->addToAssertionCount(1);
    }

    public function testUnknownAliasIsLeftToTheFieldValidator()
    {
        // WhereFieldValidator answers an unknown alias with its own 400 when the query is prepared.
        $this->validator->assertQueriedAreRequested('((milestone.name : x))', ['project'], 'Issue');
        $this->addToAssertionCount(1);
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
