<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Project;
use Gaia\MVC\Models\Repository;
use Gaia\MVC\Models\Skill;
use Gaia\MVC\Models\Toolserver;
use Gaia\MVC\Models\Workflow;

/**
 * The rule that a workspace can be deleted only when it owns nothing
 * (Workspaces D-009): no live project, repository, agent, tool server, skill
 * or workflow. Projects and agents cover agent runs and the issues assigned to
 * the workspace's agents, so those need no check of their own.
 *
 * Any delete path of a workspace asks this class before it deletes.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceDeleteGate
{
    /**
     * Owned kinds, by the label used in the refusal: the model and the
     * condition for a row that is not deleted (projects keep `deleted` in a
     * smallint column, the others in a boolean one).
     *
     * @var array
     */
    const OWNED = [
        'projects' => [Project::class, 'deleted = 0'],
        'repositories' => [Repository::class, 'deleted = false'],
        'agents' => [Agent::class, 'deleted = false'],
        'toolservers' => [Toolserver::class, 'deleted = false'],
        'skills' => [Skill::class, 'deleted = false'],
        'workflows' => [Workflow::class, 'deleted = false'],
    ];

    /**
     * How many live rows of each kind the workspace still owns; kinds with
     * none are left out.
     *
     * @param  string $workspaceId
     * @return array label => count
     */
    public function owned($workspaceId)
    {
        $counts = [];
        foreach (self::OWNED as $label => list($model, $live)) {
            $count = (int) $model::count([
                'conditions' => 'workspaceId = :workspaceId: AND ' . $live,
                'bind' => ['workspaceId' => (string) $workspaceId],
            ]);
            if ($count > 0) {
                $counts[$label] = $count;
            }
        }

        return $counts;
    }

    /**
     * Refuse the delete while the workspace owns anything.
     *
     * @param  string $workspaceId
     * @return void
     * @throws WorkspaceDeleteRefusal 409 naming the counts per kind
     */
    public function check($workspaceId)
    {
        $counts = $this->owned($workspaceId);
        if (count($counts) === 0) {
            return;
        }

        $parts = [];
        foreach ($counts as $label => $count) {
            $parts[] = $count . ' ' . $label;
        }

        throw new WorkspaceDeleteRefusal(
            'The workspace still has ' . implode(', ', $parts) . '; delete or move them first.',
            409,
            $counts
        );
    }
}
