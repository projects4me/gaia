<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Issue types controller
 *
 * System types (system = 1) are the defaults copied into new projects and are
 * managed in Settings > Issue Types; writes to them need setting.update
 * (Issuetype metadata, acl.systemRecords). Project copies are separate rows
 * (system = 0), so changing or deleting a default never touches them.
 * `?projectCount=true` adds, per type, how many projects have a type with the
 * same name (the Settings "Projects using" column).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssuetypeController extends RestController
{
    /**
     * Project counts by type name, loaded when the request asks for them.
     *
     * @var array|null
     */
    protected $projectCounts = null;

    /**
     * List types, with project counts when `projectCount=true`.
     *
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        $this->loadProjectCountsIfRequested();
        return parent::listAction();
    }

    /**
     * Get one type, with its project count when `projectCount=true`.
     *
     * @return \Phalcon\Http\Response
     */
    public function getAction()
    {
        $this->loadProjectCountsIfRequested();
        return parent::getAction();
    }

    /**
     * Add `projectCount` to every type in the response when it was requested.
     *
     * @param  array $data
     * @return \Phalcon\Http\Response
     */
    protected function returnResponse(array $data)
    {
        if ($this->projectCounts !== null && !empty($data['data']) && is_array($data['data'])) {
            if (isset($data['data']['attributes'])) {
                $data['data'] = $this->addProjectCount($data['data']);
            } else {
                foreach ($data['data'] as $index => $resource) {
                    if (is_array($resource)) {
                        $data['data'][$index] = $this->addProjectCount($resource);
                    }
                }
            }
        }

        return parent::returnResponse($data);
    }

    /**
     * Load project counts per type name when `projectCount=true`: distinct
     * non-deleted projects with a non-deleted project type of that name.
     * Counts cover every project, so they need setting.get.
     *
     * @return void
     * @throws \Gaia\Exception\Access
     */
    protected function loadProjectCountsIfRequested()
    {
        if (!filter_var($this->request->get('projectCount', null, false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ($this->getDI()->has('acl')) {
            $this->getDI()->get('acl')->checkAccess('setting.get');
        }

        $rows = $this->modelsManager->executeQuery(
            'SELECT t.name AS name, COUNT(DISTINCT t.projectId) AS total
             FROM [Gaia\MVC\Models\Issuetype] AS t
             JOIN [Gaia\MVC\Models\Project] AS p ON p.id = t.projectId
             WHERE t.system = 0 AND t.deleted = 0 AND p.deleted = 0
             GROUP BY t.name'
        );

        $this->projectCounts = [];
        foreach ($rows as $row) {
            $this->projectCounts[(string) $row->name] = (int) $row->total;
        }
    }

    /**
     * Set `projectCount` on one type resource (matched by name).
     *
     * @param  array $resource
     * @return array
     */
    protected function addProjectCount(array $resource)
    {
        if (isset($resource['attributes']['name'])) {
            $name = (string) $resource['attributes']['name'];
            $resource['attributes']['projectCount'] = isset($this->projectCounts[$name]) ? $this->projectCounts[$name] : 0;
        }

        return $resource;
    }
}
