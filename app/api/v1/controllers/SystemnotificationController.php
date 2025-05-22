<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\MVC\Models\Systemnotificationrecipient;

/**
 * System Notification Controller
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SystemnotificationController extends RestController
{
    /**
     * Handles GET requests to the notification endpoint.
     * Primarily used to mark notifications as read for a specific user.
     *
     * @return \Phalcon\Http\Response The HTTP response object
     */
    public function listAction()
    {
        global $logger, $currentUser;
        $logger->debug("Gaia.Controllers.Systemnotification->listAction");

        try {
            $response = parent::listAction();
            // Check for unread notifications
            $unreadCount = Systemnotificationrecipient::count([
                'conditions' => 'userId = :userId: AND isRead = 0',
                'bind' => ['userId' => $currentUser->id]
            ]);

            // Add unread notifications flag to response
            $responseData = json_decode($response->getContent(), true);
            $responseData['meta']['unreadCount'] = (int)$unreadCount;
            $response->setContent(json_encode($responseData));
        } catch (\Exception $e) {
            $logger->error("Gaia.Controllers.Systemnotification->listAction Error: " . $e->getMessage());
            $this->response->setStatusCode(500, "Internal Server Error");
            $this->response->setJsonContent(
                [
                'success' => false,
                'message' => 'An error occurred while processing your request'
                ]
            );
        }

        $logger->debug("-Gaia.Controllers.Systemnotification->listAction");
        return $this->response;
    }
}
