<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Agents;

use Gaia\MVC\Models\Agentskill;
use Gaia\MVC\Models\Skill;

/**
 * An agent's live skill grants, each with the skill's content.
 *
 * A grant whose skill has been deleted is left out.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentSkillGrants
{
    /**
     * The agent's live skills, in grant order.
     *
     * @param  string $agentId
     * @return array[] Each ['id', 'name', 'description', 'version', 'content']
     */
    public function forAgent($agentId)
    {
        $grants = Agentskill::find([
            'conditions' => 'agentId = :agentId: AND deleted = false',
            'bind' => ['agentId' => (string) $agentId],
            'order' => 'dateCreated, id',
        ]);

        $skills = [];
        $seen = [];
        foreach ($grants as $grant) {
            $skillId = (string) $grant->skillId;
            if ($skillId === '' || isset($seen[$skillId])) {
                continue;
            }
            $seen[$skillId] = true;

            $skill = $this->liveSkill($skillId);
            if ($skill !== null) {
                $skills[] = [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'version' => $skill->version,
                    'content' => $skill->content,
                ];
            }
        }

        return $skills;
    }

    /**
     * @param  string $skillId
     * @return Skill|null
     */
    private function liveSkill($skillId)
    {
        $skill = Skill::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => $skillId],
        ]);

        return $skill ?: null;
    }
}
