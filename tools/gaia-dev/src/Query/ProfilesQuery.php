<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * `gaia-dev profiles [name]`: the api-tester auth profiles with their role, what
 * the role grants and how many cases run as them.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProfilesQuery implements Query
{
    /**
     * {@inheritdoc}
     */
    public function run(GraphReader $graph, array $arguments, array $options): array
    {
        $profiles = isset($arguments[0]) ? [$graph->find('profile', $arguments[0])] : $graph->nodes('profile');

        $lines = [];
        foreach ($profiles as $profile) {
            $attributes = $profile['attributes'];
            $lines[] = $profile['key']
                . (isset($attributes['role']) ? ' role ' . $attributes['role'] : ' no login')
                . ' | allow ' . count($graph->out($profile, 'grants'))
                . ', members ' . count($graph->out($profile, 'grants_members'))
                . ', deny ' . count($graph->out($profile, 'denies'))
                . ' | cases ' . count($graph->in($profile, 'runs_as'))
                . (isset($attributes['roleDescription']) ? ' | ' . $attributes['roleDescription'] : '');
        }

        return [
            'title' => count($lines) . ' profiles (gaia-dev acl <resource> <profile> for one row)',
            'profiles' => $lines,
        ];
    }
}
