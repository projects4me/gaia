<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Conversations;

/**
 * Decides which activities a conversation-room change records.
 *
 * A room records only a few activities: it was started, its subject or
 * description was edited, and its linked issue was set, changed or cleared.
 * Comments and votes record none, since they show in the thread itself.
 * Each activity is a plain array (type, description, context) that the
 * caller persists.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Libraries\Conversations
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RoomActivities
{
    /**
     * The relatedTo value of a conversation-room activity.
     *
     * @var string
     */
    const RELATED_TO = 'conversationroom';

    /**
     * Edited text fields and the description of their activity.
     *
     * @var array
     */
    const EDITED_FIELDS = array(
        'subject' => 'Subject edited',
        'description' => 'Description edited',
    );

    /**
     * Edited fields whose old and new values go in the activity context.
     * Long text (the description) is left out.
     *
     * @var array
     */
    const FIELDS_WITH_VALUES = array('subject');

    /**
     * Returns the issue number of an issue id, or null when it is unknown.
     *
     * @var callable
     */
    private $issueNumberOf;

    /**
     * @param callable $issueNumberOf Returns the issue number of an issue id (or null).
     */
    public function __construct(callable $issueNumberOf)
    {
        $this->issueNumberOf = $issueNumberOf;
    }

    /**
     * The activity recorded when a room is started.
     *
     * @param  string|null $issueId The linked issue's id, if any.
     * @return array       One activity.
     */
    public function forCreate($issueId = null)
    {
        $context = array();
        if (!self::isBlank($issueId)) {
            $context = array('issueId' => $issueId, 'issueNumber' => $this->numberOf($issueId));
        }

        return array(self::activity('created', 'Thread started', $context));
    }

    /**
     * The activities recorded for an update of a room.
     *
     * @param  array $audit The update's change: field => ['old' => ..., 'new' => ...].
     * @return array Zero or more activities.
     */
    public function forUpdate(array $audit)
    {
        $activities = array();

        foreach (self::EDITED_FIELDS as $field => $description) {
            if (self::changed($audit, $field)) {
                $context = array('field' => $field);
                if (in_array($field, self::FIELDS_WITH_VALUES, true)) {
                    $context['old'] = $audit[$field]['old'];
                    $context['new'] = $audit[$field]['new'];
                }
                $activities[] = self::activity('updated', $description, $context);
            }
        }

        if (self::changed($audit, 'issueId')) {
            $activities[] = $this->issueLinkActivity($audit['issueId']['old'], $audit['issueId']['new']);
        }

        return $activities;
    }

    /**
     * The activity for a linked issue that was set, changed or cleared.
     *
     * @param  string|null $oldId The previously linked issue id.
     * @param  string|null $newId The newly linked issue id.
     * @return array       One activity.
     */
    private function issueLinkActivity($oldId, $newId)
    {
        $oldNumber = $this->numberOf($oldId);
        $newNumber = $this->numberOf($newId);
        $context = array(
            'field' => 'issueId',
            'oldIssueId' => self::isBlank($oldId) ? null : $oldId,
            'oldIssueNumber' => $oldNumber,
            'newIssueId' => self::isBlank($newId) ? null : $newId,
            'newIssueNumber' => $newNumber,
        );

        if (self::isBlank($newId)) {
            return self::activity('unlinked', 'Unlinked issue' . self::issueRef($oldNumber), $context);
        }
        if (self::isBlank($oldId)) {
            return self::activity('linked', 'Linked issue' . self::issueRef($newNumber), $context);
        }

        return self::activity(
            'linked',
            'Changed linked issue' . self::issueRef($oldNumber, ' from') . self::issueRef($newNumber, ' to'),
            $context
        );
    }

    /**
     * The issue number of an issue id, or null for a blank or unknown id.
     *
     * @param  string|null $issueId
     * @return mixed
     */
    private function numberOf($issueId)
    {
        return self::isBlank($issueId) ? null : call_user_func($this->issueNumberOf, $issueId);
    }

    /**
     * An issue reference for a description, or nothing when the number is unknown.
     *
     * @param  mixed  $number The issue number.
     * @param  string $prefix Words before the reference.
     * @return string
     */
    private static function issueRef($number, $prefix = '')
    {
        return self::isBlank($number) ? '' : "{$prefix} {{Issue@{$number}}}";
    }

    /**
     * Whether the audit holds a real change of a field. A change between
     * null and an empty string is not one.
     *
     * @param  array  $audit The update's change.
     * @param  string $field The field.
     * @return bool
     */
    private static function changed(array $audit, $field)
    {
        if (!isset($audit[$field]) || !is_array($audit[$field])) {
            return false;
        }
        $old = array_key_exists('old', $audit[$field]) ? $audit[$field]['old'] : null;
        $new = array_key_exists('new', $audit[$field]) ? $audit[$field]['new'] : null;
        if (self::isBlank($old) && self::isBlank($new)) {
            return false;
        }

        return (string) $old !== (string) $new;
    }

    /**
     * Whether a value is empty (null or an empty string).
     *
     * @param  mixed $value
     * @return bool
     */
    private static function isBlank($value)
    {
        return $value === null || $value === '';
    }

    /**
     * Builds one activity.
     *
     * @param  string $type        The activity type.
     * @param  string $description The activity description.
     * @param  array  $context     Structured details for the client.
     * @return array
     */
    private static function activity($type, $description, array $context)
    {
        return array(
            'type' => $type,
            'description' => $description,
            'context' => $context,
        );
    }
}
