<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Conversations;

use Gaia\MVC\Models\Activity;
use Gaia\MVC\Models\Issue;

use function Gaia\Libraries\Utils\create_guid as create_guid;

/**
 * Saves the activities of a conversation room as Activity rows.
 *
 * RoomActivities decides which activities a change records; this class
 * resolves linked issue numbers for it and persists what it returns.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Conversations
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RoomActivityRecorder
{
    /**
     * @var RoomActivities
     */
    private $activities;

    /**
     * @param RoomActivities|null $activities The activity policy; defaults to one that reads issue numbers from Issue.
     */
    public function __construct(RoomActivities $activities = null)
    {
        $this->activities = $activities ?: new RoomActivities(array(self::class, 'issueNumberOf'));
    }

    /**
     * Records that a room was started.
     *
     * @param  mixed $room The persisted conversation room.
     * @return void
     */
    public function recordCreated($room)
    {
        if (empty($room->id)) {
            return;
        }
        $issueId = isset($room->issueId) ? $room->issueId : null;
        $this->save($room->id, $this->activities->forCreate($issueId));
    }

    /**
     * Records the edits and linked-issue changes of an updated room.
     *
     * @param  mixed $room The updated conversation room (with its audit).
     * @return void
     */
    public function recordUpdated($room)
    {
        if (empty($room->id) || empty($room->isChanged) || !isset($room->audit) || !is_array($room->audit)) {
            return;
        }
        $this->save($room->id, $this->activities->forUpdate($room->audit));
    }

    /**
     * The issue number of an issue, or null when it does not exist.
     *
     * @param  string $issueId The issue id.
     * @return mixed
     */
    public static function issueNumberOf($issueId)
    {
        $issue = Issue::findFirst(array(
            'conditions' => 'id = :id:',
            'bind' => array('id' => $issueId),
        ));

        return $issue ? $issue->issueNumber : null;
    }

    /**
     * Saves activities related to a room. A failed save is logged, not
     * raised, so it never fails the room write.
     *
     * @param  string $roomId     The room id.
     * @param  array  $activities Activities from RoomActivities.
     * @return void
     */
    private function save($roomId, array $activities)
    {
        global $logger;

        foreach ($activities as $entry) {
            $activity = new Activity();
            $activity->id = create_guid();
            $activity->relatedTo = RoomActivities::RELATED_TO;
            $activity->relatedId = $roomId;
            $activity->type = $entry['type'];
            $activity->description = $entry['description'];
            $activity->context = empty($entry['context']) ? null : json_encode($entry['context']);
            if (!$activity->save() && $logger) {
                $logger->error("Gaia.Conversations.RoomActivityRecorder: could not save the '{$entry['type']}' activity of room {$roomId}");
            }
        }
    }
}
