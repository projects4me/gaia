<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Config;

/**
 * Notification Configuration Class
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components\Notifications\Config
 * @category Config
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationConfig
{
    /**
     * Map of controller names to their corresponding notification module classes
     *
     * @var array
     */
    protected $moduleMap;

    /**
     * Constructor - initializes the mapping between controllers and notification modules
     */
    public function __construct()
    {
        $this->moduleMap = [
            'issue' => 'IssueNotification',
            'membership' => 'MembershipNotification',
            'comment' => 'CommentNotification',
            'timelog' => 'TimelogNotification',
            'milestone' => 'MilestoneNotification'
        ];
    }

    /**
     * Get the notification module class name for a given controller
     *
     * @param  string $controllerName The name of the controller
     * @return string|null The notification module class name or null if not found
     */
    public function getModuleClass($controllerName)
    {
        return isset($this->moduleMap[$controllerName]) ?
            $this->moduleMap[$controllerName] : null;
    }
}
