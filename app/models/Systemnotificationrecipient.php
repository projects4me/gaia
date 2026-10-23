<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\MVC\REST\Controllers\Components\Events\NotificationLiveEvents;

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
     * Fan the parent notification to the recipient as a domain event.
     *
     * Nested recipient rows are not created through REST, so this hook
     * delegates to NotificationLiveEvents instead of a controller mixin.
     *
     * @method afterCreate
     * @return void
     */
    public function afterCreate()
    {
        global $logger;

        try {
            $notification = Systemnotification::findFirstById($this->systemNotificationId);
            if (!$notification) {
                return;
            }
            (new NotificationLiveEvents())->publishCreated($this, $notification);
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Notification publish failed: ' . $e->getMessage());
            }
        }
    }

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

    /**
     * Marks a user's unread notifications about one entity as read
     *
     * @param string $userId    The ID of the user whose notifications should be marked as read
     * @param string $relatedTo The type of the entity the notifications belong to, e.g. issue
     * @param string $relatedId The ID of the entity the notifications belong to
     * @return int The number of notifications marked as read
     * @throws \RuntimeException When a row cannot be updated; no rows are changed then
     */
    public static function markRelatedAsRead($userId, $relatedTo, $relatedId)
    {
        $di = \Phalcon\Di::getDefault();
        $recipients = $di->get('modelsManager')->createBuilder()
            ->from(['recipient' => self::class])
            ->innerJoin(Systemnotification::class, 'sn.id = recipient.systemNotificationId', 'sn')
            ->where(
                'recipient.userId = :userId: AND recipient.isRead = 0'
                . ' AND sn.relatedTo = :relatedTo: AND sn.relatedId = :relatedId:',
                ['userId' => $userId, 'relatedTo' => $relatedTo, 'relatedId' => $relatedId]
            )
            ->getQuery()
            ->execute();

        // Update row by row, not with one UPDATE, so model behaviours
        // (e.g. modified user and date) still run. All rows change or none.
        $db = $di->get('db');
        $db->begin();
        try {
            $markedCount = 0;
            foreach ($recipients as $recipient) {
                $recipient->isRead = 1;
                if (!$recipient->update()) {
                    throw new \RuntimeException(
                        'Could not mark notification recipient ' . $recipient->id . ' as read'
                    );
                }
                $markedCount++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return $markedCount;
    }

    /**
     * Counts a user's unread notifications
     *
     * @param string $userId The ID of the user
     * @return int The number of unread notifications
     */
    public static function countUnread($userId)
    {
        return (int)self::count([
            'conditions' => 'userId = :userId: AND isRead = 0',
            'bind' => ['userId' => $userId]
        ]);
    }
}
