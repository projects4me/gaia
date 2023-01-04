<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use ElephantIO\Client,
ElephantIO\Engine\SocketIO\Version1X;

/**
 * Conversers Controller
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ConverserController extends RestController
{
  /**
   * This function saves a converser which is done via the RestController.
   * In addition to saving we add a user to the room in the Hermes as well.
   *
   * @return \Phalcon\Http\Response
   * @throws \Phalcon\Exception
   * @todo Get the host from configuration file
   * @todo Implement multi-tenancy
   */
  public function postAction(){

    // Call the parent so that the Conversation room can be saved
    $response = parent::postAction();
    $converser = json_decode($response->getContent())->data;

    // If the Conversation room was saved then add it to Hermes
    if ($response->getStatusCode() == '201 Created')
    {

      // Todo- link
      $host = 'http://localhost:3000';

      // The tenant id - Must be initialized in the bootstrap
      $tenant = 'abc';

      // Establish a connection with Hermes
      $client = new Client(new Version1X($host));
      $client->initialize();


      // Using the namespace of Gaia and add the user to the room
      $client->of('/gaia');

      // If the converser is a user then add the user to the conversation
      if ($converser->relatedTo == 'user')
      {
        print "Inviting ".$converser->relatedId." to room ".$converser->conversationRoomId;
        $client->emit('invite',['room'=>$converser->conversationRoomId,'tenant'=>$tenant,'user'=>$converser->relatedId]);
        $client->close();
      }
      // else if project then add all the users associated with the project to the conversation
      else if($converser->relatedTo == 'project')
      {
        // fetch the users of th project and add them all to the project conversation
      }
    }
    return $response;
  }
}
