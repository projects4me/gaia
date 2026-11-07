<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\Clause;
use Gaia\Exception\BadRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Clause::prepareWhere: values travel as bound parameters, never inside the PHQL, and fields
 * must name a known field of the model or of one of its relationships (agaia-4302).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ClauseTest extends TestCase
{
    /**
     * @var Clause
     */
    protected $clause;

    /**
     * @var object
     */
    protected $query;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }

        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('metaManager', new ClauseTestMetaManager(), true);
        $this->clause = new Clause($di);
        $this->query = (object) ['modelAlias' => 'Issue', 'modelId' => null];
    }

    public function testApostropheValueIsBoundNotInlined()
    {
        $this->clause->prepareWhere("(Issue.subject CONTAINS O'Brien)", $this->query);

        $this->assertSame('(Issue.subject LIKE :q0:)', $this->clause->where);
        $this->assertSame(['q0' => "%O'Brien%"], $this->clause->getBindParams());
    }

    public function testLegacyEscapedApostropheIsReadAsApostrophe()
    {
        $this->clause->prepareWhere("(Issue.subject : O\\'Brien)", $this->query);

        $this->assertSame(['q0' => "O'Brien"], $this->clause->getBindParams());
    }

    public function testInjectionAttemptIsBoundLiterally()
    {
        $this->clause->prepareWhere("(Issue.subject : x' OR '1'='1)", $this->query);

        $this->assertSame('(Issue.subject = :q0:)', $this->clause->where);
        $this->assertSame(['q0' => "x' OR '1'='1"], $this->clause->getBindParams());
    }

    public function testBracketedInjectionAttemptIsRejected()
    {
        $this->expectException(BadRequest::class);

        $this->clause->prepareWhere("((Issue.subject CONTAINS x') OR ('1'='1))", $this->query);
    }

    public function testLikeWildcardsInValueAreEscaped()
    {
        $this->clause->prepareWhere('(Issue.subject STARTS 100%_done)', $this->query);

        $this->assertSame(['q0' => '100\\%\\_done%'], $this->clause->getBindParams());
    }

    public function testContainsListBindsOnePlaceholderPerItem()
    {
        $this->clause->prepareWhere("(Issue.projectId CONTAINS 'a','b','c')", $this->query);

        $this->assertSame('(Issue.projectId IN (:q0:, :q1:, :q2:))', $this->clause->where);
        $this->assertSame(['q0' => 'a', 'q1' => 'b', 'q2' => 'c'], $this->clause->getBindParams());
    }

    public function testBetweenBindsBothBounds()
    {
        $this->clause->prepareWhere("(Issue.issueNumber !BETWEEN '1' AND '9')", $this->query);

        $this->assertSame('(!(Issue.issueNumber BETWEEN :q0: AND :q1:))', $this->clause->where);
        $this->assertSame(['q0' => '1', 'q1' => '9'], $this->clause->getBindParams());
    }

    public function testComparisonOperatorsAndConnectorsAreKept()
    {
        $this->clause->prepareWhere('((Issue.issueNumber >: 5) AND (project.name !: Gaia))', $this->query);

        $this->assertSame('((Issue.issueNumber >= :q0:) AND (project.name != :q1:))', $this->clause->where);
        $this->assertSame(['q0' => '5', 'q1' => 'Gaia'], $this->clause->getBindParams());
    }

    public function testNullaryOperatorsBindNothing()
    {
        $this->clause->prepareWhere('((Issue.subject NULL) OR (Issue.subject !EMPTY))', $this->query);

        $this->assertSame("((Issue.subject IS NULL) OR (Issue.subject != ''))", $this->clause->where);
        $this->assertSame([], $this->clause->getBindParams());
    }

    public function testBareFieldOfTheModelIsAccepted()
    {
        $this->clause->prepareWhere('(subject : x)', $this->query);

        $this->assertSame('(subject = :q0:)', $this->clause->where);
    }

    /**
     * @dataProvider invalidFields
     */
    public function testInvalidFieldIsABadRequest($statement)
    {
        $this->expectException(BadRequest::class);

        $this->clause->prepareWhere($statement, $this->query);
    }

    public function invalidFields()
    {
        return [
            'unknown field' => ['(Issue.nope : x)'],
            'unknown alias' => ['(Nowhere.subject : x)'],
            'unknown related field' => ['(project.nope : x)'],
            'not an identifier' => ['(Issue.subject;DROP : x)'],
            'expression' => ['(1=1 : x)'],
            'no operator' => ['(Issue.subject)'],
            'model name that is not a relationship' => ['(Project.name : x)'],
            'unknown operator' => ['(Issue.subject = x)'],
            'unbalanced parenthesis' => ['((Issue.subject : x)'],
            'no parenthesised condition' => ['Issue.subject : x'],
            'tautology after a leaf' => ['(Issue.subject : x) OR 1=1'],
            'statement separator after a leaf' => ['(Issue.subject : x); DELETE FROM Issue'],
            'comment after a leaf' => ['(Issue.subject : x) --'],
        ];
    }

    public function testNestedGroupsWithAndOrAreAccepted()
    {
        $this->clause->prepareWhere(
            '(((Issue.subject : a) OR (Issue.subject : b)) and (Issue.issueNumber > 1))',
            $this->query
        );

        $this->assertSame(
            '(((Issue.subject = :q0:) OR (Issue.subject = :q1:)) and (Issue.issueNumber > :q2:))',
            $this->clause->where
        );
    }

    public function testSplitQueryReplacementDropsUnusedBinds()
    {
        $this->clause->prepareWhere('((project.name : Gaia) AND (Issue.subject : x))', $this->query);

        $this->clause->updateBaseWhereWithIds(
            ['p1', 'p2'],
            ['primaryKey' => 'projectId'],
            'Issue',
            '(project.name = :q0:)'
        );

        $this->assertSame('((Issue.projectId IN(:q2:, :q3:)) AND (Issue.subject = :q1:))', $this->clause->where);
        $this->assertSame(['q1' => 'x', 'q2' => 'p1', 'q3' => 'p2'], $this->clause->getBindParams());
    }

    public function testRelatedWhereWithoutIdsMatchesNothing()
    {
        $this->clause->updateRelatedWhere([], ['rhsKey' => 'issueId'], 'Watcher');

        $this->assertSame('(Watcher.issueId IN(:q0:))', $this->clause->where);
        $this->assertSame(['q0' => ''], $this->clause->getBindParams());
    }

    public function testQuotedValueMayHoldBrackets()
    {
        $this->clause->prepareWhere("(Issue.subject CONTAINS 'fix (urgent)')", $this->query);

        $this->assertSame('(Issue.subject LIKE :q0:)', $this->clause->where);
        $this->assertSame(['q0' => '%fix (urgent)%'], $this->clause->getBindParams());
    }

    public function testDoubledApostropheInQuotedValueIsOneApostrophe()
    {
        $this->clause->prepareWhere("(Issue.subject : 'O''Brien')", $this->query);

        $this->assertSame('(Issue.subject = :q0:)', $this->clause->where);
        $this->assertSame(['q0' => "O'Brien"], $this->clause->getBindParams());
    }

    public function testQuotedListWithSpacesBindsEachName()
    {
        $this->clause->prepareWhere("(project.name CONTAINS 'Rana Nouman','Hammad Hassan')", $this->query);

        $this->assertSame('(project.name IN (:q0:, :q1:))', $this->clause->where);
        $this->assertSame(['q0' => 'Rana Nouman', 'q1' => 'Hammad Hassan'], $this->clause->getBindParams());
    }

    public function testQuotedListItemsMayHoldCommasBracketsAndApostrophes()
    {
        $this->clause->prepareWhere("(Issue.subject !CONTAINS 'a, b','(c)','O''Brien')", $this->query);

        $this->assertSame('(Issue.subject NOT IN (:q0:, :q1:, :q2:))', $this->clause->where);
        $this->assertSame(['q0' => 'a, b', 'q1' => '(c)', 'q2' => "O'Brien"], $this->clause->getBindParams());
    }

    public function testKeywordsInsideQuotesArePlainText()
    {
        $this->clause->prepareWhere(
            "((Issue.subject : 'NULL') AND (Issue.subject CONTAINS 'x) OR (y') AND (Issue.issueNumber BETWEEN 'A AND B' AND 'C'))",
            $this->query
        );

        $this->assertSame(
            '((Issue.subject = :q0:) AND (Issue.subject LIKE :q1:) AND (Issue.issueNumber BETWEEN :q2: AND :q3:))',
            $this->clause->where
        );
        $this->assertSame(
            ['q0' => 'NULL', 'q1' => '%x) OR (y%', 'q2' => 'A AND B', 'q3' => 'C'],
            $this->clause->getBindParams()
        );
    }

    public function testRepeatedLeavesAreEachTranslatedInPlace()
    {
        $this->clause->prepareWhere(
            "((Issue.subject : '(Issue.subject : a)') OR (Issue.subject : a))",
            $this->query
        );

        $this->assertSame('((Issue.subject = :q0:) OR (Issue.subject = :q1:))', $this->clause->where);
        $this->assertSame(['q0' => '(Issue.subject : a)', 'q1' => 'a'], $this->clause->getBindParams());
    }

    public function testUnquotedNullSentinelStillMeansIsNull()
    {
        $this->clause->prepareWhere('(Issue.subject : null)', $this->query);

        $this->assertSame('(Issue.subject IS NULL)', $this->clause->where);
    }

    public function testUnclosedQuoteIsReadAsBefore()
    {
        $this->clause->prepareWhere("(Issue.subject : 'abc)", $this->query);

        $this->assertSame(['q0' => 'abc'], $this->clause->getBindParams());
    }

    public function testIdIsBound()
    {
        $this->clause->updateBaseWhereWithId('Gaia\\MVC\\Models\\Issue', "1' OR '1'='1");

        $this->assertSame('Issue.id = :q0:', $this->clause->where);
        $this->assertSame(['q0' => "1' OR '1'='1"], $this->clause->getBindParams());
    }
}

/**
 * Minimal metadata: Issue with a `project` relationship to Project.
 */
class ClauseTestMetaManager
{
    public function getModelMeta($model)
    {
        $fields = [
            'Issue' => ['id' => [], 'subject' => [], 'projectId' => [], 'issueNumber' => []],
            'Project' => ['id' => [], 'name' => []],
        ];

        return ['fields' => $fields[$model] ?? []];
    }

    public function getRelationshipMeta($modelAlias, $relationshipName, $throwException = true)
    {
        if ($modelAlias === 'Issue' && $relationshipName === 'project') {
            return ['relatedModel' => 'Gaia\\MVC\\Models\\Project'];
        }
        return [];
    }

    public function getRelatedModelName($modelAlias, $relName)
    {
        return 'Project';
    }
}
