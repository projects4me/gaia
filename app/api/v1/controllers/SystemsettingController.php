<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * System Settings/Configurations Controller
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SystemsettingController extends RestController
{

    /**
     * This method is used to return list of system configurations.
     *
     * @method listAction
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        $configPaths = [
            'aclSettings' => [
                'acl.permissionFlags',
                'acl.apiOptions'
            ]
        ];

        $dataArray = [
            "data" => [
                [
                    "type" => "systemsetting",
                    "attributes" => [
                        "aclSettings" => []
                    ],
                    "id" => "systemsetting_1"
                ]
            ]
        ];

        // Get all system level configurations.
        global $settings;
        $systemConfigurations = $settings['system'];

        foreach ($configPaths as $settingsModule => $paths) {
            foreach ($paths as $path) {
                list(, $type) = explode(".", $path);
                $dataArray["data"][0]["attributes"][$settingsModule][$type] = json_encode($systemConfigurations->path($path));
            }
        }

        $this->finalData = $this->buildHAL($dataArray);
        return $this->returnResponse($this->finalData);
    }
}
