<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\NodeConfigRules;
use Gaia\Libraries\Workflows\NodeKindCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Tests the node config rules and the references the catalog reads from a
 * config. Row lookups are stubbed, so no database is needed.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NodeConfigRulesTest extends TestCase
{
    public function testAcceptsAValidConfig()
    {
        $this->assertSame([], $this->rules()->errors('actor', 'run_agent', ['agentId' => 'agent-1', 'prompt' => 'Plan it']));
        $this->assertSame([], $this->rules()->errors('actor', 'ask_user', ['userId' => '{issue.assigneeId}', 'question' => 'OK?']));
        $this->assertSame([], $this->rules()->errors('trigger', 'issue.created', []));
        $this->assertSame([], $this->rules()->errors('condition', 'filter', [
            'conditions' => [['field' => 'issue.priority', 'operator' => 'equals', 'value' => 'High']],
        ]));
    }

    /**
     * @dataProvider invalidConfigs
     */
    public function testRejectsAnInvalidConfig($category, $kind, array $config, $message)
    {
        $this->assertContains($message, $this->rules()->errors($category, $kind, $config));
    }

    public function invalidConfigs()
    {
        $case = function ($key) {
            return ['key' => $key, 'conditions' => [['field' => 'f', 'operator' => 'equals']]];
        };

        return [
            'missing required' => ['actor', 'run_agent', [], 'config.agentId is required for run_agent.'],
            'unknown setting' => ['action', 'end', ['note' => 'x'], 'config.note is not a setting of end.'],
            'missing agent' => ['actor', 'run_agent', ['agentId' => 'gone'], 'config.agentId must name an existing Agent.'],
            'not a string' => ['action', 'set_status', ['status' => ['Open']], 'config.status must be a non-empty string.'],
            'bad operator' => ['condition', 'filter', ['conditions' => [['field' => 'f', 'operator' => 'like']]],
                'config.conditions[0].operator must be one of ' . implode(', ', NodeConfigRules::OPERATORS) . '.'],
            'conditions not a list' => ['condition', 'filter', ['conditions' => ['field' => 'f']],
                'config.conditions must be a non-empty list of conditions.'],
            'duplicate case' => ['condition', 'switch', ['cases' => [$case('a'), $case('a')]],
                "config.cases[1].key must be unique and not 'default'."],
            'default case' => ['condition', 'switch', ['cases' => [$case('default')]],
                "config.cases[0].key must be unique and not 'default'."],
        ];
    }

    public function testReferencesLeaveOutExpressions()
    {
        $catalog = new NodeKindCatalog();

        $this->assertSame(
            [['entityType' => 'Agent', 'entityId' => 'agent-1']],
            $catalog->references('actor', 'run_agent', ['agentId' => 'agent-1'])
        );
        $this->assertSame([], $catalog->references('action', 'assign', ['assigneeId' => '{issue.assigneeId}']));
        $this->assertSame(
            [['entityType' => 'User', 'entityId' => 'user-1']],
            $catalog->references('action', 'notify', ['userId' => 'user-1', 'message' => 'Done'])
        );
    }

    private function rules()
    {
        return new class () extends NodeConfigRules {
            protected function exists($model, $id)
            {
                return $id !== 'gone';
            }
        };
    }
}
