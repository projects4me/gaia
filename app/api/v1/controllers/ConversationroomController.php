<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;



/**
 * ConversationRooms Controller
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ConversationroomController extends RestController
{
  /**
   * This function saves a conversation room which is done via the RestController.
   * In addition to saving we add the created room in the Hermes as well.
   *
   * @return \Phalcon\Http\Response
   * @throws \Phalcon\Exception
   * @todo Get the host from configuration file
   * @todo Implement multi-tenancy
   */
  public function postAction(){

    // Call the parent so that the Conversation room can be saved
    $response = parent::postAction();

    // // If the Conversation room was saved then add it to Hermes
    // if ($response->getStatusCode() == '201 Created')
    // {

    //   // Todo- link
    //   $host = 'http://localhost:3000';

    //   // The tenant id - Must be initialized in the bootstrap
    //   $tenant = 'abc';

    //   // Establish a connection with Hermes
    //   $client = new Client(new Version2X($host));
    //   $client->initialize();

    //   // Using the namespace of Gaia create the room and register the current user to it
    //   $client->of('/gaia');
    //   $client->emit('createRoom', ['room'=>json_decode($response->getContent())->data->id,'tenant'=>$tenant,'user'=>$GLOBALS['currentUser']->id]);
    //   $client->close();
    // }
    return $response;
  }
}
