<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\MVC\REST\Controllers\AclAdminController;
use Gaia\Libraries\Utils\Util;
use function Gaia\Libraries\Utils\create_guid;

/**
 * Resource Controller
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceController extends AclAdminController
{
    /**
     * Project authorization flag
     * @var bool
     */
    protected $projectAuthorization = false;

    /**
     * System level flag
     * @var bool
     */
    protected $systemLevel = true;

    /**
     * This method is used to create resource from the database. In this method postAction() method of
     * AclAdmin controller is called first to check whether authenticated user has admin rights.
     * 
     * @method postAction
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        $this->callParent = false;
        parent::postAction();

        $util = new Util();
        $data = array();

        $requestData = $util->objectToArray($this->request->getJsonRawBody());
        $parentEntity = $requestData['parentEntity'];

        $newId = create_guid();
        $requestData['id'] = $newId;

        unset($requestData['parentEntity']);

        $resource = \Gaia\MVC\Models\Resource::addResource($parentEntity, $requestData);

        $this->response->setJsonContent(array('status' => 'OK'));
        $this->response->setStatusCode(201, "Created");

        $data = $resource->read(array('id' => $newId));
        $dataArray = $this->extractData($data, 'one');
        $finalData = $this->buildHAL($dataArray);

        return $this->returnResponse($finalData);
    }
}
