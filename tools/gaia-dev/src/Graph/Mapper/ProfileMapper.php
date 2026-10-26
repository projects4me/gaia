<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;

/**
 * Maps the harness profiles onto profile nodes (user and role) and links each
 * to the permissions its role holds: `grants` (allowed), `grants_members`
 * (allowed for the user's member projects only) and `denies`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProfileMapper
{
    /**
     * Value of a permission row => edge type; any other positive value grants.
     */
    private const MEMBERS_SCOPE = 2;

    /**
     * @param Graph $graph
     * @param array[] $profiles ProfileExtractor records
     */
    public function map(Graph $graph, array $profiles)
    {
        foreach ($profiles as $profile) {
            $profileId = $graph->addNode(Schema::PROFILE, $profile['name'], [
                'userId' => $profile['user']['id'],
                'email' => $profile['user']['email'],
                'roleId' => $profile['role']['id'] ?? null,
                'role' => $profile['role']['name'] ?? null,
                'roleDescription' => $profile['role']['description'] ?? null,
            ]);

            foreach ($profile['permissions'] as $resource => $allowed) {
                $graph->addEdge(
                    $profileId,
                    $this->edgeType((int) $allowed),
                    $graph->addNode(Schema::PERMISSION, $resource)
                );
            }
        }
    }

    /**
     * @param int $allowed
     * @return string
     */
    private function edgeType(int $allowed): string
    {
        if ($allowed === self::MEMBERS_SCOPE) {
            return 'grants_members';
        }

        return $allowed > 0 ? 'grants' : 'denies';
    }
}
