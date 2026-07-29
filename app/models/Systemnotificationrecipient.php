<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * System Notification Recipient Model
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\Models
 * @category System Notification
 * @license  http://legal.projects4.me/LICENSE.txt AGPLv3
 */
class Systemnotificationrecipient extends Model
{

    /**
     * Marks all notifications as read for a specific user
     *
     * @param int $userId The ID of the user whose notifications should be marked as read
     * @return \Phalcon\Mvc\Model\Query\Status The result of the update operation
     */
    public static function updateAllAsRead($userId)
    {
        $di = \Phalcon\Di::getDefault();
        $query = $di->get('modelsManager')->createQuery("UPDATE \Gaia\MVC\Models\Systemnotificationrecipient SET isRead = 1 WHERE userId = :userId:");
        return $query->execute(['userId' => $userId]);
    }
}
