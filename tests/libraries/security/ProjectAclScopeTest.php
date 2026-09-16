<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;

/**
 * Pure-helper coverage for Project membership record-scope builders.
 *
 * Loads Project.php directly so these assertions run without Phalcon model bootstrap.
 * Skips if Gaia\Core\MVC\Models\Model is unavailable.
 */
class ProjectAclScopeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists('Gaia\\Core\\MVC\\Models\\Model', false)
            && !class_exists('Gaia\\Core\\MVC\\Models\\Model', true)
        ) {
            return;
        }

        if (!class_exists('Gaia\\MVC\\Models\\Project', false)) {
            $path = dirname(__DIR__, 3) . '/app/models/Project.php';
            if (defined('APP_PATH')) {
                $path = APP_PATH . '/app/models/Project.php';
            }
            require_once $path;
        }
    }

    protected function setUp(): void
    {
        if (!class_exists('Gaia\\MVC\\Models\\Project', false)
            && !class_exists('Gaia\\MVC\\Models\\Project', true)
        ) {
            $this->markTestSkipped('Project model could not be loaded without Phalcon');
        }
    }

    public function testNormalizeGroupKeyBindingAcceptsFieldOrPaths()
    {
        $this->assertSame(
            ['field' => 'projectId'],
            \Gaia\MVC\Models\Project::normalizeGroupKeyBinding(null)
        );
        $this->assertSame(
            ['field' => 'projectId'],
            \Gaia\MVC\Models\Project::normalizeGroupKeyBinding('projectId')
        );
        $paths = ['paths' => [['relatedModel' => 'Issue']]];
        $this->assertSame(
            $paths,
            \Gaia\MVC\Models\Project::normalizeGroupKeyBinding($paths)
        );
    }

    public function testSelfBindingUsesId()
    {
        $this->assertSame(
            ['field' => 'id'],
            \Gaia\MVC\Models\Project::selfBinding()
        );
    }

    public function testBuildAclWhereForDirectField()
    {
        $where = \Gaia\MVC\Models\Project::buildAclWhere(
            'Issue',
            ['field' => 'projectId'],
            ['proj-a', 'proj-b']
        );
        $this->assertSame(
            "Issue.projectId IN ('proj-a','proj-b')",
            $where
        );
    }

    public function testBuildAclWhereEmptyIdsDeniesAll()
    {
        $this->assertSame(
            '1 = 0',
            \Gaia\MVC\Models\Project::buildAclWhere('Project', ['field' => 'id'], [])
        );
    }

    public function testBuildAclWhereForPaths()
    {
        $where = \Gaia\MVC\Models\Project::buildAclWhere(
            'Comment',
            [
                'paths' => [
                    [
                        'when' => ['relatedTo' => 'issues'],
                        'relatedModel' => 'Issue',
                        'localKey' => 'relatedId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                ],
            ],
            ['proj-a']
        );

        $this->assertStringContainsString("Comment.relatedTo = 'issues'", $where);
        $this->assertStringContainsString(
            'SELECT Issue.id FROM [Gaia\\MVC\\Models\\Issue] AS Issue',
            $where
        );
        $this->assertStringContainsString("Issue.projectId IN ('proj-a')", $where);
    }

    public function testBuildAclWhereForPathsSkipsRedundantSelfSubquery()
    {
        $where = \Gaia\MVC\Models\Project::buildAclWhere(
            'Activity',
            [
                'paths' => [
                    [
                        'when' => ['relatedTo' => 'project'],
                        'relatedModel' => 'Project',
                        'localKey' => 'relatedId',
                        'relatedKey' => 'id',
                        'projectField' => 'id',
                    ],
                    [
                        'when' => ['relatedTo' => 'issue'],
                        'relatedModel' => 'Issue',
                        'localKey' => 'relatedId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                ],
            ],
            ['proj-a']
        );

        $this->assertStringContainsString(
            "(Activity.relatedTo = 'project' AND Activity.relatedId IN ('proj-a'))",
            $where
        );
        $this->assertStringNotContainsString('FROM Project', $where);
        $this->assertStringContainsString(
            'SELECT Issue.id FROM [Gaia\\MVC\\Models\\Issue] AS Issue',
            $where
        );
    }
}
