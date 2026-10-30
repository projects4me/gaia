<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Workflows\ActorStepRefusal;
use Gaia\Libraries\Workflows\ActorStepReports;
use Gaia\Libraries\Workflows\ActorSteps;
use Gaia\MVC\REST\Controllers\Components\Events\WorkflowTriggersComponent;

/**
 * Workflowactorstep Controller
 *
 * The workflow steps waiting for an actor. An agent's runtime reads its
 * step (GET), claims it (PATCH `status: claimed`) and reports its outcome
 * (PATCH `outcome`, `comment`); the workflow then goes on (ActorStepReports).
 * Both are fields on the standard PATCH (docs/dev/bundles.md C), not
 * routes of their own; the domain checks the state change, so the update
 * does not go through the generic model save.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowactorstepController extends RestController
{
    /**
     * Claims the step or reports its outcome, then returns the step.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        global $currentUser;

        $update = $this->extractRequestAttributes();
        if (empty($update['id']) && !empty($this->id)) {
            $update['id'] = $this->id;
        }
        if (empty($update['id'])) {
            return $this->refuse(new ActorStepRefusal('The step id is required.', 422));
        }
        $this->id = (string) $update['id'];
        $update = $this->authorizeRecordUpdate($update);

        try {
            $this->reports()->update($this->id, $currentUser->id, $update);
        } catch (ActorStepRefusal $refusal) {
            return $this->refuse($refusal);
        }

        return $this->getAction();
    }

    /**
     * @return ActorStepReports
     */
    protected function reports()
    {
        return new ActorStepReports(new ActorSteps(), (new WorkflowTriggersComponent())->runner());
    }

    /**
     * @param  ActorStepRefusal $refusal
     * @return \Phalcon\Http\Response
     */
    private function refuse(ActorStepRefusal $refusal)
    {
        $this->response->setStatusCode($refusal->getCode());
        $this->response->setJsonContent(['status' => 'ERROR', 'messages' => [$refusal->getMessage()]]);

        return $this->response;
    }
}
