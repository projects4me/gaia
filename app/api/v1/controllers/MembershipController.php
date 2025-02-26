<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\MVC\Models\Membership;

/**
 * Memberships Controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MembershipController extends RestController
{
    /**
     * Project authorization flag
     *
     * @var bool
     */
    protected $projectAuthorization = false;

    /**
     * System level flag
     *
     * @var bool
     */
    protected $systemLevel = true;

    /**
     * Components that this controller uses.
     *
     * @var  $uses
     * @type array
     */
    public $uses = array('ProjectActivities');

    /**
     * Delete action to handle membership deletion and reassign in-progress issues to a new assignee.
     *
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        global $logger;
        $logger->debug('MembershipController::deleteAction() | Assigning issues to (given new assignee) member');

        $membership = Membership::findById($this->id)->getFirst();
        $newAssigneeId = $this->request->get('newAssigneeId');

        if ($this->checkAssignee($membership, $newAssigneeId)) {
            $queryBuilder = $this->getDI()->get('modelsManager')->createBuilder();
            $query = $queryBuilder
                ->from(['i' => 'Gaia\MVC\Models\Issue'])
                ->innerJoin(
                    'Gaia\MVC\Models\Issuestatus',
                    'is2.projectId = i.projectId AND is2.id = i.statusId',
                    'is2'
                )
                ->where(
                    'i.assignee = :assignee:', [
                    'assignee' => $membership->userId
                    ]
                )
                ->andWhere(
                    'is2.done = :done:', [
                    'done' => '0'
                    ]
                )
                ->andWhere(
                    'i.projectId = :projectId:', [
                    'projectId' => $membership->relatedId
                    ]
                );
            
            $issues = $query->getQuery()->execute();

            if ($issues) {
                foreach ($issues as $issue) {
                    $issue->assignee = $newAssigneeId;
                    $issue->save();
                }
            }
        }

        return parent::deleteAction();
    }

    /**
     * Validates if the new assignee is a member of the project
     *
     * @param  Membership $membership    Current membership being deleted
     * @param  int        $newAssigneeId ID of the new assignee
     * @return bool True if assignee is valid
     * @throws \Gaia\Exception\Exception If assignee is not a project member
     */
    protected function checkAssignee(Membership $membership, $newAssigneeId)
    {
        global $logger;
        $logger->debug('MembershipController::checkAssignee() | Checking if assignee is a member of the project');

        $newAssigneeMembership = Membership::findFirst(
            [
            'conditions' => 'userId = :userId: AND relatedId = :relatedId: AND relatedTo = "project"',
            'bind' => [
                'userId' => $newAssigneeId,
                'relatedId' => $membership->relatedId
            ]
            ]
        );

        if (!$newAssigneeMembership) {
            $logger->error('MembershipController::checkAssignee() | Assignee is not a member of the project');
            throw new \Gaia\Exception\Exception('Assignee is not a member of the project');
        }

        return true;
    }
}
