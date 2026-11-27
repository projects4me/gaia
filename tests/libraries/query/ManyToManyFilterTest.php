<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\ManyToManyFilter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ManyToManyFilter: a where leaf on a hasManyToMany relationship becomes a subquery on the record's
 * id, so it needs no join and cannot multiply records (Tags D-002, agaia-4987).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ManyToManyFilterTest extends TestCase
{
    /**
     * @var ManyToManyFilter
     */
    protected $filter;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }

        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('metaManager', new ManyToManyFilterTestMetaManager(), true);
        $this->filter = new ManyToManyFilter($di);
    }

    public function testTagLeafBecomesASubqueryOnTheRecordId()
    {
        $this->assertSame(
            '(Issue.id IN (SELECT tagTagged.relatedId FROM Gaia\\MVC\\Models\\Tagged tagTagged'
            . ' WHERE (tagTagged.relatedTo = "issue") AND tagTagged.tagId IN'
            . ' (SELECT tag.id FROM Gaia\\MVC\\Models\\Tag tag WHERE (tag.tag = :q0:))))',
            $this->filter->rewrite('Issue', 'tag', '(tag.tag = :q0:)')
        );
    }

    public function testRelationshipWithoutConditionIsRewritten()
    {
        $this->assertSame(
            '(Project.id IN (SELECT membersMembership.projectId FROM Gaia\\MVC\\Models\\Membership membersMembership'
            . ' WHERE membersMembership.userId IN (SELECT members.id FROM Gaia\\MVC\\Models\\User members'
            . ' WHERE (members.id = :q0:))))',
            $this->filter->rewrite('Project', 'members', '(members.id = :q0:)')
        );
    }

    public function testOtherRelationshipTypesAndTheModelItselfAreLeftAlone()
    {
        $this->assertSame('(assignedTo.name = :q0:)', $this->filter->rewrite('Issue', 'assignedTo', '(assignedTo.name = :q0:)'));
        $this->assertSame('(Issue.subject = :q0:)', $this->filter->rewrite('Issue', 'Issue', '(Issue.subject = :q0:)'));
        $this->assertSame('(nothing.name = :q0:)', $this->filter->rewrite('Issue', 'nothing', '(nothing.name = :q0:)'));
        $this->assertFalse($this->filter->supports('Issue', 'assignedTo'));
    }

    public function testConditionReadingTheBaseModelKeepsTheJoin()
    {
        // The subquery has only the junction in scope, so Chatroom.createdUser could not be read there.
        $this->assertFalse($this->filter->supports('Chatroom', 'conversers'));
        $this->assertSame(
            '(conversers.name = :q0:)',
            $this->filter->rewrite('Chatroom', 'conversers', '(conversers.name = :q0:)')
        );
    }

    public function testExclusiveConditionKeepsTheJoin()
    {
        $this->assertFalse($this->filter->supports('User', 'badgeLevels'));
    }

    public function testSupportedRelationships()
    {
        $this->assertTrue($this->filter->supports('Issue', 'tag'));
        $this->assertTrue($this->filter->supports('Project', 'members'));
    }
}

/**
 * Meta manager stub with the shapes of real hasManyToMany relationships.
 */
class ManyToManyFilterTestMetaManager
{
    public function getRelationshipMeta($modelAlias, $relationshipName, $throwException = true)
    {
        $rels = [
            'Issue' => [
                'tag' => [
                    'type' => 'hasManyToMany',
                    'primaryKey' => 'id',
                    'relatedModel' => '\\Gaia\\MVC\\Models\\Tagged',
                    'rhsKey' => 'relatedId',
                    'lhsKey' => 'tagId',
                    'secondaryModel' => '\\Gaia\\MVC\\Models\\Tag',
                    'secondaryKey' => 'id',
                    'condition' => 'tagTagged.relatedTo = "issue"',
                ],
                'assignedTo' => ['type' => 'hasOne', 'relatedModel' => '\\Gaia\\MVC\\Models\\User'],
            ],
            'Project' => [
                'members' => [
                    'type' => 'hasManyToMany',
                    'primaryKey' => 'id',
                    'relatedModel' => '\\Gaia\\MVC\\Models\\Membership',
                    'rhsKey' => 'projectId',
                    'lhsKey' => 'userId',
                    'secondaryModel' => '\\Gaia\\MVC\\Models\\User',
                    'secondaryKey' => 'id',
                ],
            ],
            'Chatroom' => [
                'conversers' => [
                    'type' => 'hasManyToMany',
                    'primaryKey' => 'id',
                    'relatedModel' => '\\Gaia\\MVC\\Models\\Converser',
                    'rhsKey' => 'chatRoomId',
                    'lhsKey' => 'userId',
                    'secondaryModel' => '\\Gaia\\MVC\\Models\\User',
                    'secondaryKey' => 'id',
                    'condition' => 'Chatroom.createdUser != conversersConverser.userId',
                ],
            ],
            'User' => [
                'badgeLevels' => [
                    'type' => 'hasManyToMany',
                    'primaryKey' => 'id',
                    'relatedModel' => '\\Gaia\\MVC\\Models\\Scoreboard',
                    'rhsKey' => 'userId',
                    'lhsKey' => 'badgeId',
                    'secondaryModel' => '\\Gaia\\MVC\\Models\\Badgelevel',
                    'secondaryKey' => 'badgeId',
                    'lhsConditionExclusive' => 'badgeLevelsScoreboard.score >= badgeLevels.min_criteria',
                ],
            ],
        ];

        return $rels[$modelAlias][$relationshipName] ?? [];
    }
}
