<?php

namespace  Gaia\MVC\REST\Controllers;

use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Project;
use Gaia\Libraries\Utils\Util;

/**
 * This controller handles issue planning operations using AI/LLM services.
 * It provides functionality to generate detailed plans for issues including
 * tasks and test cases based on issue details and project context.
 *
 * The controller integrates with LLM services to provide intelligent
 * issue planning capabilities for project management workflows.
 *
 * @package  Gaia\MVC\REST\Controllers
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class IssueplanningController extends \Phalcon\Mvc\Controller
{
    /**
     * Only create is exposed — this module only accepts POST /issueplanning.
     * Declared for the ACL catalog (permission UI); runtime auth is not via RestController.
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'create' => array(
            'action' => 'create',
            'controllerAction' => 'postAction',
        ),
    );

    /**
     * This function is used to generate an issue plan using AI/LLM services.
     *
     * The method processes issue details and project context to generate
     * comprehensive planning data including tasks and test cases. It fetches
     * the issue and project information from the database and passes it to
     * the LLM service for intelligent plan generation.
     *
     * @method postAction
     *
     * @return \Phalcon\Http\Response JSON response containing the generated plan
     * @throws \Exception When issue is not found or processing fails
     */
    public function postAction()
    {
        $util = new Util();
        $data = array();

        $requestData = $util->objectToArray($this->request->getJsonRawBody());
        $llmService = $this->di->get('serviceFactory')::create('llm');
        $issueNumber = $requestData['issueNumber'];

        // Fetch the issue details from the database
        $issue = Issue::findFirstByIssueNumber($issueNumber);

        if (!$issue) {
            $this->response->setJsonContent(
                [
                'success' => false,
                'message' => 'An error occurred while processing your request'
                ]
            );
            return $this->response;
        }

        $project = Project::findFirstById($issue->projectId);
        $issueDetails = [
            'projectName' => $project->name,
            'issueDescription' => $issue->description,
            'issueSubject' => $issue->subject
        ];

        // Call the AI service to generate tasks and test cases
        $result = $llmService->generateIssuePlan($issueDetails);
        // Return the result as a JSON response
        return $this->response->setJsonContent([
            'success' => true,
            'message' => 'Issue plan generated successfully',
            'data' => $result
        ]);
    }
}
