<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event;
use Gaia\Libraries\Utils\Util;
use function Gaia\Libraries\Utils\create_guid as create_guid;
use \Gaia\MVC\Models\Activity;
use \Gaia\MVC\Models\Conversationroom;
use \Gaia\MVC\Models\Issue;

/**
 * This component is used to handle the creation of activity upon mention of an issue in a comment
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components
 * @category Component
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssuementionComponent
{
    /**
     * This function handles the creation of activity upon mention of an issue in a comment
     *
     * @param Event $event
     * @param $controller
     * @param $model
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        try {
            global $logger;
            $util = new Util();
            $requestData = $util->objectToArray($controller->request->getJsonRawBody());
            $mentionedIssues = $requestData['meta']['mentionedIssues'];
            $conversationId = $requestData['data']['attributes']['relatedId'];
            $conversation  = Conversationroom::findFirstById($conversationId);
            foreach ($mentionedIssues as $issueNumber) {
                if ($issueNumber == $conversation->issueNumber) {
                    continue;
                }
                $issue = Issue::findFirstByIssueNumber($issueNumber);
                $activity = new Activity();
                $activity->id = create_guid();
                $activity->description = "Mentioned this issue
                                        in {{Conversationroom@{$conversation->id}}}
                                        of issue {{Issue@{$conversation->issueNumber}}}";
                $activity->relatedTo = 'issue';
                $activity->relatedId = $issue->id;
                $activity->type = 'mentioned';
                $activity->context = json_encode([
                    'conversationId' => $conversation->id,
                    'issueNumber' => $conversation->issueNumber,
                    'projectShortcode' => $conversation->projectShortcode,
                ]);
                $activity->save();
            }
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception($e->getMessage());
        }
        $logger->debug('Gaia.Controller.Component.Issuemention::afterCreate()');
    }
}
