<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

use GaiaDev\Query\GraphReader;

/**
 * The ACL matrix: one row per resource, one column per auth profile, one letter
 * per action the profile may perform.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclMatrixPage implements PageBuilder
{
    /**
     * action => letter in a cell
     */
    private const ACTIONS = ['get' => 'g', 'create' => 'c', 'update' => 'u', 'delete' => 'd'];

    /**
     * {@inheritdoc}
     */
    public function build(GraphReader $graph): array
    {
        $profiles = array_keys($graph->nodes('profile'));
        $allowed = [];
        foreach ($graph->nodes('profile') as $profile) {
            foreach (['grants' => false, 'grants_members' => true] as $edge => $members) {
                foreach ($graph->out($profile, $edge) as $permission) {
                    $allowed[$profile['key']][$permission['key']] = $members;
                }
            }
        }

        $rows = [];
        foreach (array_keys($graph->nodes('controller')) as $resource) {
            $row = [$resource];
            foreach ($profiles as $profile) {
                $row[] = $this->cell($allowed, $profile, $resource);
            }
            $rows[] = $row;
        }

        $lines = Markdown::page('ACL matrix', 'the seeded roles in tools/api-tester/harness/prepare-test-db.sh');
        $lines = array_merge($lines, Markdown::section('How to read', [
            '- One row per resource, one column per api-tester auth profile.',
            '- A cell lists the actions the profile may perform: `g` get, `c` create, `u` update, `d` delete.',
            '- An upper case letter allows the action on project members only; `-` means not granted.',
            '- `gaia-dev acl <resource> [profile]` explains one resource, including field permissions.',
        ]));
        $lines = array_merge(
            $lines,
            Markdown::section('Matrix', Markdown::table(array_merge(['Resource'], $profiles), $rows))
        );

        return ['acl-matrix.md' => Markdown::render($lines)];
    }

    /**
     * @param array $allowed profile => permission key => whether limited to members
     * @param string $profile
     * @param string $resource
     * @return string e.g. "gC--"
     */
    private function cell(array $allowed, string $profile, string $resource): string
    {
        $cell = '';
        foreach (self::ACTIONS as $action => $letter) {
            $key = $resource . '.' . $action;
            if (!isset($allowed[$profile][$key])) {
                $cell .= '-';
            } else {
                $cell .= $allowed[$profile][$key] ? strtoupper($letter) : $letter;
            }
        }

        return $cell;
    }
}
