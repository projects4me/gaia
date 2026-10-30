<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Workflows\TriggerEventDescriber;

/**
 * Workflow Event Controller
 *
 * Read-only catalog for the workflow editor: `GET /api/v1/workflowevent`
 * lists every trigger (the domain events and the schedule) with its module,
 * watched fields and the payload paths and types filters and expressions can
 * use (see `TriggerEventDescriber`). Reading it needs `workflowevent.get`.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkfloweventController extends RestController
{
    private const LIST_ONLY = 'Workflow events are read as a list.';

    /**
     * Only the list is exposed; both GET routes are checked against workflowevent.get.
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'get' => array(
            'action' => 'get',
            'controllerAction' => 'getAction',
        ),
        'list' => array(
            'action' => 'get',
            'controllerAction' => 'listAction',
        ),
    );

    /**
     * Every trigger, as a JSON:API collection whose ids are the trigger kinds.
     *
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        $data = array();
        foreach ((new TriggerEventDescriber())->all() as $trigger) {
            $data[] = array(
                'type' => 'workflowevent',
                'id' => $trigger['name'],
                'attributes' => $trigger,
            );
        }

        return $this->returnResponse(array(
            'data' => $data,
            'meta' => array(
                'count' => count($data),
                'links' => array('self' => $this->request->getURI()),
            ),
        ));
    }

    /**
     * Trigger kinds hold dots, which the id route doesn't take: read the list.
     *
     * @throws \Gaia\Exception\ResourceNotFound
     * @return void
     */
    public function getAction()
    {
        throw new \Gaia\Exception\ResourceNotFound(self::LIST_ONLY);
    }
}
