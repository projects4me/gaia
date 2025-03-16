<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Issues Controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\MVC\REST\Controllers;
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueController extends RestController
{
    /**
     * This components that this controller uses
     *
     * @var  $uses
     * @type array
     */
    public $uses = array('Filethumb','Issueactivities');

    /**
     * This method retrieves the project ID from the request and constructs a query string
     * to filter issues by the specified project ID. If the project ID is not provided,
     * an exception is thrown.
     *
     * @throws \Gaia\Exception\Exception If the project ID is not provided in the request.
     * @return string The query string for filtering issues by project ID.
     */
    protected function getAdditionalQueryForExport()
    {
        $projectId = $this->request->get('projectId');

        if (empty($projectId)) {
            throw new \Gaia\Exception\Exception("Project id is required to export issues.");
        }

        $query = "((Issue.projectId : $projectId))";
        return $query;
    }
}
