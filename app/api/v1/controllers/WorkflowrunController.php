<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Workflows\TestRunPayload;
use Gaia\Libraries\Workflows\TestRunRefusal;
use Gaia\Libraries\Workflows\WorkflowTestRun;
use Gaia\MVC\REST\Controllers\Components\Events\Support\LiveRecordEventEnvelopes;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Workflowrun Controller
 *
 * The run history is read with the standard GET (list, filter by `workflowId`
 * or `entityType` and `entityId`, include `steps`); the engine writes it, so
 * there is no PUT, PATCH or DELETE. A POST with `test: true` is a test run:
 * the workflow runs against the chosen `entityId` with its actions simulated
 * (nothing is written, no agent runs) and the response is the run, with the
 * path taken and each step's resolved inputs, without saving it. A POST
 * without `test` is refused. The record's project must be one the user can
 * read and in the workflow's scope; the record's fields the user may not read
 * are left out of the run.
 *
 * Attributes of a test run: `workflowId`, `entityId` (the record the trigger
 * is about), optional `entityType`, `triggerNodeId` (when the workflow has
 * several triggers), `changes` (field => {before, after}, for update triggers),
 * `actorPorts` (actor node id => the port its simulated step takes) and
 * `projectId` (for a schedule trigger).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowrunController extends RestController
{
    /**
     * Simulates a run of the workflow and returns it.
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        $attributes = $this->extractRequestAttributes();
        if (empty($attributes['test'])) {
            return $this->refuse(
                new TestRunRefusal('Runs are written by the workflow engine; send test: true to test run a workflow.', 422)
            );
        }
        if (empty($attributes['workflowId'])) {
            return $this->refuse(new TestRunRefusal('workflowId is required.', 422));
        }

        $workflowId = (string) $attributes['workflowId'];
        $changes = $this->arrayAttribute($attributes, 'changes');
        $actorPorts = $this->arrayAttribute($attributes, 'actorPorts');

        try {
            $run = new WorkflowTestRun();
            $trigger = $run->trigger($workflowId, $this->attribute($attributes, 'triggerNodeId'));
            $payload = (new TestRunPayload(new LiveRecordEventEnvelopes(), $this->acl()))->build(
                $trigger,
                $this->attribute($attributes, 'entityType'),
                $this->attribute($attributes, 'entityId'),
                $changes,
                $this->attribute($attributes, 'projectId')
            );
            $this->assertProjectReadable(isset($payload['projectId']) ? $payload['projectId'] : null);
            $result = $run->run($workflowId, $trigger['id'], $payload, $actorPorts);
        } catch (TestRunRefusal $refusal) {
            return $this->refuse($refusal);
        }

        return $this->returnResponse(array(
            'data' => array(
                'type' => 'workflowrun',
                'id' => create_guid(),
                'attributes' => array('test' => true) + $result,
            ),
        ));
    }

    /**
     * A test run shows the record's fields, so the record's project must be
     * one the user can read. A run without a project (a record outside any
     * project, a schedule for none) is only for users who see every project.
     *
     * @param  string|null $projectId
     * @return void
     * @throws \Gaia\Exception\Access
     */
    private function assertProjectReadable($projectId)
    {
        if ($this->acl() !== null) {
            $this->acl()->assertProjectIdInScope((string) $projectId);
        }
    }

    /**
     * The request's ACL, or null when authorization is off.
     *
     * @return \Gaia\Libraries\Security\Acl|null
     */
    private function acl()
    {
        return $this->getDI()->has('acl') ? $this->getDI()->get('acl') : null;
    }

    /**
     * @param  array  $attributes
     * @param  string $name
     * @return string|null
     */
    private function attribute(array $attributes, $name)
    {
        return isset($attributes[$name]) && $attributes[$name] !== '' ? (string) $attributes[$name] : null;
    }

    /**
     * @param  array  $attributes
     * @param  string $name
     * @return array
     */
    private function arrayAttribute(array $attributes, $name)
    {
        return isset($attributes[$name]) && is_array($attributes[$name]) ? $attributes[$name] : [];
    }

    /**
     * @param  TestRunRefusal $refusal
     * @return \Phalcon\Http\Response
     */
    private function refuse(TestRunRefusal $refusal)
    {
        $this->response->setStatusCode($refusal->getCode());
        $this->response->setJsonContent(['status' => 'ERROR', 'messages' => [$refusal->getMessage()]]);

        return $this->response;
    }
}
