<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Utils\Util;
use Gaia\MVC\Models\Systemnotificationrecipient;

/**
 * Handles API endpoints for managing system notification recipients.
 * Provides functionality to mark notifications as read and manage
 * notification status for users.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SystemnotificationrecipientController extends RestController
{
    /**
     * System notification recipient endpoints are not action-ACL managed.
     *
     * @var bool
     */
    protected $aclAllowed = false;

    /**
     * Handles POST requests to the notification recipient endpoint.
     * Marks the current user's notifications as read: all of them
     * (markAllAsRead) or those about one entity (relatedTo and relatedId).
     * Any userId in the body is ignored.
     *
     * @return \Phalcon\Http\Response The HTTP response object
     */
    public function postAction()
    {
        global $logger, $currentUser;
        $logger->debug("Gaia.Controllers.Systemnotificationrecipient->postAction");

        try {
            $util = new Util();
            $requestData = $util->objectToArray($this->request->getJsonRawBody());
            if (!is_array($requestData)) {
                $requestData = [];
            }
            $markAllAsRead = filter_var($requestData['markAllAsRead'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $relatedTo = $requestData['relatedTo'] ?? null;
            $relatedId = $requestData['relatedId'] ?? null;
            $hasRelated = is_string($relatedTo) && $relatedTo !== '' && is_string($relatedId) && $relatedId !== '';

            if ($markAllAsRead) {
                $result = Systemnotificationrecipient::updateAllAsRead($currentUser->id);
                if (!$result->success()) {
                    throw new \Exception('An error occurred while processing your request');
                }
                $responseData = [
                    'success' => true,
                    'message' => 'All notifications marked as read'
                ];
            } elseif ($hasRelated) {
                $responseData = [
                    'success' => true,
                    'markedCount' => Systemnotificationrecipient::markRelatedAsRead(
                        $currentUser->id,
                        $relatedTo,
                        $relatedId
                    )
                ];
            } else {
                $this->response->setStatusCode(400, "Bad Request");
                $this->response->setJsonContent(
                    [
                    'success' => false,
                    'message' => 'Send markAllAsRead, or both relatedTo and relatedId'
                    ]
                );
                $logger->debug("-Gaia.Controllers.Systemnotificationrecipient->postAction");
                return $this->response;
            }

            $responseData['meta']['unreadCount'] = Systemnotificationrecipient::countUnread($currentUser->id);
            $this->response->setStatusCode(200, "OK");
            $this->response->setJsonContent($responseData);
            $logger->debug("-Gaia.Controllers.Systemnotificationrecipient->postAction");
        } catch (\Exception $e) {
            $logger->error("Gaia.Controllers.Systemnotificationrecipient->postAction Error: " . $e->getMessage());
            $this->response->setStatusCode(500, "Internal Server Error");
            $this->response->setJsonContent(
                [
                'success' => false,
                'message' => 'An error occurred while processing your request'
                ]
            );
        }

        return $this->response;
    }

    /**
     * Handles PATCH requests to the notification recipient endpoint.
     * Primarily used to mark notifications as read for a specific user.
     *
     * @return \Phalcon\Http\Response The HTTP response object
     */
    public function patchAction()
    {
        global $logger, $currentUser;
        $logger->debug("Gaia.Controllers.Systemnotificationrecipient->patchAction");
        
        try {
            $response = parent::patchAction();
            
            // Check for unread notifications
            $unreadCount = Systemnotificationrecipient::countUnread($currentUser->id);
            
            // Add unread notifications flag to response
            $responseData = json_decode($response->getContent(), true);
            $responseData['meta']['unreadCount'] = $unreadCount;
            $response->setContent(json_encode($responseData));
            
            $logger->debug("-Gaia.Controllers.Systemnotificationrecipient->patchAction");
            return $response;
        } catch (\Exception $e) {
            $logger->error("Gaia.Controllers.Systemnotificationrecipient->patchAction Error: " . $e->getMessage());
            $this->response->setStatusCode(500, "Internal Server Error");
            $this->response->setJsonContent(
                [
                'success' => false,
                'message' => 'An error occurred while processing your request'
                ]
            );
        }

        $logger->debug("-Gaia.Controllers.Systemnotificationrecipient->patchAction");
        return $this->response;
    }
}
