<?php

/*
 * Projects4Me Community Edition is an open source project management software
 * developed by PROJECTS4ME Inc. Copyright (C) 2015-2016 PROJECTS4ME Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 (GNU AGPL v3) as
 * published be the Free Software Foundation with the addition of the following
 * permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF
 * THE COVERED WORK IN WHICH THE COPYRIGHT IS OWNED BY PROJECTS4ME Inc.,
 * Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.
 *
 * You should have received a copy of the GNU AGPL v3 along with this program;
 * if not, see http://www.gnu.org/licenses or write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * You can contact PROJECTS4ME, Inc. at email address contact@projects4.me.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU AGPL v3.
 *
 * In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal
 * Notices must retain the display of the "Powered by Projects4Me" logo. If the
 * display of the logo is not reasonably feasible for technical reasons, the
 * Appropriate Legal Notices must display the words "Powered by Projects4Me".
 */

use Foundation\Mvc\RestController;
use ElephantIO\Client,
ElephantIO\Engine\SocketIO\Version1X;



/**
 * ConversationRooms Controller
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ChatroomController extends RestController
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

      // Using the namespace of Gaia create the room and register the current user to it
      $client->of('/gaia');
      $client->emit('createRoom', ['room'=>json_decode($response->getContent())->data->id,'tenant'=>$tenant,'user'=>$GLOBALS['currentUser']->id]);
      $client->close();
    }
    return $response;
  }
}
