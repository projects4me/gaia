<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tools;

use Gaia\MVC\Models\Tool;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Makes a server's tools rows match the tools it listed, by name: a new
 * name is added, a known one gets the listed description and input schema
 * (and is restored if it was deleted), and a name no longer listed is
 * soft-deleted. Ids are kept, so grants on a tool survive a refresh.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolCatalogSync
{
    /**
     * Store the listed tools for the server. Run it in a transaction.
     *
     * @param  string $toolServerId
     * @param  array  $listed List of ['name', 'description', 'inputSchema'] (see McpToolLister)
     * @return array ['added' => int, 'updated' => int, 'removed' => int]
     * @throws ToolRefreshException When a row cannot be saved
     */
    public function sync($toolServerId, array $listed)
    {
        $stored = [];
        foreach ($this->storedTools($toolServerId) as $tool) {
            $stored[(string) $tool->name] = $tool;
        }

        $counts = ['added' => 0, 'updated' => 0, 'removed' => 0];
        $seen = [];
        foreach ($listed as $entry) {
            $name = (string) $entry['name'];
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            if (isset($stored[$name])) {
                $tool = $stored[$name];
                if (!$this->changed($tool, $entry)) {
                    continue;
                }
                $counts['updated']++;
            } else {
                $tool = new Tool();
                $tool->id = create_guid();
                $tool->toolServerId = (string) $toolServerId;
                $tool->name = $name;
                $counts['added']++;
            }

            $tool->description = $entry['description'];
            $tool->inputSchema = $entry['inputSchema'];
            $this->save($tool);
        }

        foreach ($stored as $name => $tool) {
            if (isset($seen[$name]) || $this->isDeleted($tool)) {
                continue;
            }
            if (!$tool->delete()) {
                throw new ToolRefreshException('The tool ' . $name . ' could not be removed from the catalog.');
            }
            $counts['removed']++;
        }

        return $counts;
    }

    /**
     * Every tools row of the server, deleted ones included.
     *
     * @param  string $toolServerId
     * @return iterable
     */
    protected function storedTools($toolServerId)
    {
        return Tool::find([
            'conditions' => 'toolServerId = :toolServerId:',
            'bind' => ['toolServerId' => (string) $toolServerId],
        ]);
    }

    /**
     * Whether the stored tool differs from the listed one or is deleted.
     *
     * @param  Tool  $tool
     * @param  array $entry
     * @return bool
     */
    private function changed(Tool $tool, array $entry)
    {
        return $this->isDeleted($tool)
            || $tool->description !== $entry['description']
            || $tool->inputSchema !== $entry['inputSchema'];
    }

    /**
     * @param  Tool $tool
     * @return bool
     */
    private function isDeleted(Tool $tool)
    {
        return filter_var($tool->deleted, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Save a row; a save that fails stops the refresh.
     *
     * @param  Tool $tool
     * @return void
     * @throws ToolRefreshException
     */
    private function save(Tool $tool)
    {
        if (!$tool->save()) {
            throw new ToolRefreshException('The tool ' . $tool->name . ' could not be saved in the catalog.');
        }
    }
}
