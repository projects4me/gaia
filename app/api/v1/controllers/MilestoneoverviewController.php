<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Issuestatus;
use Gaia\MVC\Models\Timelog;

/**
 * Milestone Overview Controller
 *
 * Provides a summarized overview of a milestone including open/closed issue
 * counts and optionally aggregated estimated vs. spent time across all issues
 * belonging to the milestone.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MilestoneoverviewController extends RestController
{
    /**
     * Only get is exposed — this module has no create/update/delete endpoints.
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'get' => array(
            'action' => 'get',
            'controllerAction' => 'getAction',
        ),
    );

    /**
     * Handles GET requests for milestone overview data.
     *
     * Fetches all issues belonging to the given milestone and iterates through
     * them to compute open/closed counts. When the `includeHours` query
     * parameter is set to 1, it also accumulates estimated and spent time
     * from the related timelogs of each issue.
     *
     * @return \Phalcon\Http\Response
     */
    public function getAction()
    {
        global $logger;
        $logger->debug('Gaia.Controllers.Milestoneoverview->getAction');

        try {
            if (!(isset($this->id) && !empty($this->id))) {
                $this->response->setStatusCode(400, 'Bad Request');
                $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'Milestone ID is required.'
                ]);
                return $this->response;
            }

            $milestoneId  = $this->id;
            $includeHours = (bool) $this->request->get('includeHours', null, false);

            $issues = Issue::find([
                'conditions' => 'milestoneId = :milestoneId: AND deleted = 0',
                'bind'       => ['milestoneId' => $milestoneId]
            ]);

            $issuesStatuses = Issuestatus::find([
                'conditions' => 'done = :done: AND deleted = 0',
                'bind'       => ['done' => 1]
            ]);

            $doneStatuses = [];
            foreach ($issuesStatuses as $status) {
                $doneStatuses[] = $status->name;
            }

            $openIssues   = 0;
            $closedIssues = 0;

            $estimated = ['days' => 0, 'hours' => 0, 'minutes' => 0];
            $spent     = ['days' => 0, 'hours' => 0, 'minutes' => 0];

            foreach ($issues as $issue) {
                $status = $issue->status;
                if ($status && in_array($status, $doneStatuses)) {
                    $closedIssues++;
                } else {
                    $openIssues++;
                }

                if ($includeHours) {
                    $timelogs = $this->getTimelogs($issue->id, 'est');
                    $estimated['days']    += $timelogs['days'];
                    $estimated['hours']   += $timelogs['hours'];
                    $estimated['minutes'] += $timelogs['minutes'];
                    $timelogs = $this->getTimelogs($issue->id, 'spent');
                    $spent['days']    += $timelogs['days'];
                    $spent['hours']   += $timelogs['hours'];
                    $spent['minutes'] += $timelogs['minutes'];
                }
            }

            $data = [
                'milestoneId'  => $milestoneId,
                'openIssues'   => $openIssues,
                'closedIssues' => $closedIssues,
            ];

            if ($includeHours) {
                $data['estimated'] = $estimated;
                $data['spent']     = $spent;
            }

            $logger->debug('-Gaia.Controllers.Milestoneoverview->getAction');
            return $this->returnResponse(['data' => $data]);
        } catch (\Exception $e) {
            $logger->error('Gaia.Controllers.Milestoneoverview->getAction Error: ' . $e->getMessage());
            $this->response->setStatusCode(500, 'Internal Server Error');
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'An error occurred while processing your request.'
            ]);
            return $this->response;
        }
    }
    
    /**
     * Get the timelogs for an issue.
     *
     * @param int $issueId The ID of the issue.
     * @param string $context The context of the timelog.
     * @return array The timelogs.
     */
    protected function getTimelogs($issueId, $context)
    {
        $timelogs = Timelog::find([
            'conditions' => 'issueId = :issueId: AND context = :context: AND deleted = 0',
            'bind'       => ['issueId' => $issueId, 'context' => $context]
        ]);
        $days = 0;
        $hours = 0;
        $minutes = 0;
        foreach ($timelogs as $timelog) {
            $days    += (int) $timelog->days ?? 0;
            $hours   += (int) $timelog->hours ?? 0;
            $minutes += (int) $timelog->minutes ?? 0;
        }

        return ['days' => $days, 'hours' => $hours, 'minutes' => $minutes];
    }
}
