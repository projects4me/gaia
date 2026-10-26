<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * `gaia-dev acl <resource> [profile]`: what each auth profile may do on a resource
 * and why (the role's rule and the scope), or one profile's full row.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclQuery implements Query
{
    /**
     * edge type => verdict shown
     */
    private const VERDICTS = ['grants' => 'allow', 'grants_members' => 'members', 'denies' => 'deny'];

    /**
     * {@inheritdoc}
     */
    public function run(GraphReader $graph, array $arguments, array $options): array
    {
        if (!isset($arguments[0])) {
            throw new QueryException('Usage: gaia-dev acl <resource> [profile]');
        }
        $controller = $graph->find('controller', $arguments[0]);
        $resource = $controller['key'];
        $permissions = $this->permissions($graph, $resource);

        $answer = ['title' => 'ACL ' . $resource];
        $models = $graph->out($controller, 'serves');
        if ($models && !empty($models[0]['attributes']['acl'])) {
            $answer['model acl'] = json_encode($models[0]['attributes']['acl'], JSON_UNESCAPED_SLASHES);
        }
        $scoped = array_keys(array_filter($permissions, function ($permission) {
            return !empty($permission['attributes']['scoped']);
        }));
        if ($scoped) {
            $answer['scoped'] = implode(', ', $scoped) . ' (members = project members only)';
        }

        if (isset($arguments[1])) {
            return array_merge($answer, $this->profileRow($graph, $graph->find('profile', $arguments[1]), $permissions));
        }

        $rows = [];
        foreach ($graph->nodes('profile') as $profile) {
            $verdicts = $this->verdicts($graph, $profile);
            $cells = [];
            foreach ($permissions as $key => $permission) {
                if ($permission['attributes']['kind'] === 'action') {
                    $cells[] = substr($key, strlen($resource) + 1) . '='
                        . (isset($verdicts[$key]) ? $verdicts[$key] : '-');
                }
            }
            $rows[] = $profile['key'] . ': ' . implode(' ', $cells)
                . (isset($profile['attributes']['role']) ? '  (role ' . $profile['attributes']['role'] . ')' : '');
        }
        $answer['profiles'] = $rows;

        return $answer;
    }

    /**
     * @param GraphReader $graph
     * @param string $resource
     * @return array[] permission key => node: the resource's action permissions and field permissions
     */
    private function permissions(GraphReader $graph, string $resource): array
    {
        $found = [];
        foreach ($graph->nodes('permission') as $key => $permission) {
            if (strpos($key, $resource . '.') !== 0) {
                continue;
            }
            $isAction = $permission['attributes']['kind'] === 'action'
                && strpos(substr($key, strlen($resource) + 1), '.') === false;
            if ($isAction || $permission['attributes']['kind'] === 'field') {
                $found[$key] = $permission;
            }
        }

        return $found;
    }

    /**
     * @param GraphReader $graph
     * @param array $profile
     * @return string[] permission key => allow|members|deny
     */
    private function verdicts(GraphReader $graph, array $profile): array
    {
        $verdicts = [];
        foreach (self::VERDICTS as $edge => $verdict) {
            foreach ($graph->out($profile, $edge) as $permission) {
                $verdicts[$permission['key']] = $verdict;
            }
        }

        return $verdicts;
    }

    /**
     * @param GraphReader $graph
     * @param array $profile
     * @param array[] $permissions
     * @return array
     */
    private function profileRow(GraphReader $graph, array $profile, array $permissions): array
    {
        $attributes = $profile['attributes'];
        $verdicts = $this->verdicts($graph, $profile);
        $rows = [];
        foreach ($permissions as $key => $permission) {
            $rows[] = $key . ' = ' . (isset($verdicts[$key]) ? $verdicts[$key] : 'not granted')
                . (!empty($permission['attributes']['scoped']) ? ' (scoped)' : '');
        }

        return [
            'profile' => $profile['key'] . (isset($attributes['role']) ? ' (role ' . $attributes['role'] . ')' : ''),
            'why' => isset($attributes['roleDescription']) ? $attributes['roleDescription'] : 'no seeded role',
            'permissions' => $rows,
        ];
    }
}
