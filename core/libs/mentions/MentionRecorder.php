<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Mentions;

use Gaia\Libraries\Workflows\ProjectMembers;
use Gaia\MVC\Models\Mention;
use Gaia\MVC\Models\User;
use Gaia\MVC\REST\Controllers\Components\Events\Support\ProjectIdResolver;

use function Gaia\Libraries\Utils\create_guid as create_guid;

/**
 * Records the users a record's text mentions as Mention rows.
 *
 * Rows are append-only: one per record and user, never removed when the
 * mention is, and not created again when it comes back, so nobody is told
 * twice. Only active users who are members of the record's project are
 * recorded, and never the actor who wrote the mention.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Mentions
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MentionRecorder
{
    /**
     * @var MentionParser
     */
    private $parser;

    /**
     * @var ProjectIdResolver
     */
    private $projects;

    /**
     * @var ProjectMembers
     */
    private $members;

    /**
     * @param MentionParser|null     $parser
     * @param ProjectIdResolver|null $projects
     * @param ProjectMembers|null    $members
     */
    public function __construct(
        MentionParser $parser = null,
        ProjectIdResolver $projects = null,
        ProjectMembers $members = null
    ) {
        $this->parser = $parser ?: new MentionParser();
        $this->projects = $projects ?: new ProjectIdResolver();
        $this->members = $members ?: new ProjectMembers();
    }

    /**
     * Records the users the record's fields mention that have no Mention row
     * for the record yet.
     *
     * @param  mixed       $record  A saved model.
     * @param  array       $fields  The fields that hold mentions.
     * @param  string|null $actorId The user who saved the record.
     * @return array The ids of the users recorded.
     */
    public function record($record, array $fields, $actorId)
    {
        if (empty($record->id)) {
            return [];
        }

        $userIds = array_values(array_diff($this->mentionedUserIds($record, $fields), [(string) $actorId]));
        if (empty($userIds)) {
            return [];
        }

        $relatedTo = $this->relatedTo($record);
        $userIds = array_values(array_diff($userIds, $this->recordedUserIds($relatedTo, (string) $record->id, $userIds)));
        if (empty($userIds)) {
            return [];
        }

        $projectId = $this->projects->resolve($record);
        if (empty($projectId)) {
            return [];
        }

        $active = $this->activeUserIds($userIds);
        $recorded = [];
        foreach ($userIds as $userId) {
            if (in_array($userId, $active, true) && $this->members->isMember($projectId, $userId)
                && $this->save($relatedTo, (string) $record->id, (string) $projectId, $userId)
            ) {
                $recorded[] = $userId;
            }
        }

        return $recorded;
    }

    /**
     * The distinct users mentioned across the fields, in field order.
     *
     * @param  mixed $record
     * @param  array $fields
     * @return array
     */
    private function mentionedUserIds($record, array $fields)
    {
        $userIds = [];
        foreach ($fields as $field) {
            $text = isset($record->{$field}) ? $record->{$field} : null;
            $userIds = array_merge($userIds, $this->parser->userIds($text));
        }

        return array_values(array_unique($userIds));
    }

    /**
     * The relatedTo of the record's mentions: its model's name, in lowercase.
     *
     * @param  mixed $record
     * @return string
     */
    private function relatedTo($record)
    {
        $parts = explode('\\', get_class($record));

        return strtolower(end($parts));
    }

    /**
     * The given users who already have a Mention row for the record,
     * deleted or not, so a row is never created twice.
     *
     * @param  string $relatedTo
     * @param  string $relatedId
     * @param  array  $userIds
     * @return array
     */
    protected function recordedUserIds($relatedTo, $relatedId, array $userIds)
    {
        $rows = Mention::find([
            'columns' => 'userId',
            'conditions' => 'relatedTo = :relatedTo: AND relatedId = :relatedId: AND userId IN ({userIds:array})',
            'bind' => ['relatedTo' => $relatedTo, 'relatedId' => $relatedId, 'userIds' => $userIds],
        ]);

        return $this->column($rows, 'userId');
    }

    /**
     * The given users who exist and are not deleted.
     *
     * @param  array $userIds
     * @return array
     */
    protected function activeUserIds(array $userIds)
    {
        $rows = User::find([
            'columns' => 'id',
            'conditions' => 'id IN ({userIds:array}) AND deleted = 0',
            'bind' => ['userIds' => $userIds],
        ]);

        return $this->column($rows, 'id');
    }

    /**
     * Saves a Mention row. Its createdUser is the actor, set by the
     * createdUserBehavior of Mention. A failed save is logged, not raised.
     *
     * @param  string $relatedTo
     * @param  string $relatedId
     * @param  string $projectId
     * @param  string $userId
     * @return bool
     */
    protected function save($relatedTo, $relatedId, $projectId, $userId)
    {
        global $logger;

        $mention = new Mention();
        $mention->id = create_guid();
        $mention->relatedTo = $relatedTo;
        $mention->relatedId = $relatedId;
        $mention->projectId = $projectId;
        $mention->userId = $userId;
        $mention->isNotified = false;
        if ($mention->save()) {
            return true;
        }

        if ($logger) {
            $logger->error("Gaia.Mentions.MentionRecorder: could not record the mention of user {$userId} in {$relatedTo} {$relatedId}");
        }
        return false;
    }

    /**
     * The values of a column of a result set, as strings.
     *
     * @param  iterable $rows
     * @param  string   $column
     * @return array
     */
    private function column($rows, $column)
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = (string) $row->{$column};
        }

        return $values;
    }
}
