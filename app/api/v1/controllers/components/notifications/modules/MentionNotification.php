<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Notifications\Modules;

use Gaia\MVC\Models\Mention;
use Gaia\Workflows\Actions\CreateModel;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * Builds the system notification that tells a user they were mentioned. It is
 * invoked for a Mention row (not a controller), once per row, from the model
 * hook, and marks the row as notified.
 *
 * @class   MentionNotification
 * @package Gaia\MVC\REST\Controllers\Components\Notifications\Modules
 */
class MentionNotification
{
    /**
     * @var RecipientService
     */
    protected $recipientService;

    /**
     * @param RecipientService|null $recipientService Service that adds the recipient.
     */
    public function __construct($recipientService = null)
    {
        $this->recipientService = $recipientService ?: new RecipientService();
    }

    /**
     * The ids of the users mentioned in a record, so the record's own
     * notification can leave them out.
     *
     * @param  string $relatedTo The model name in lowercase.
     * @param  string $relatedId The record id.
     * @return array
     */
    public static function mentionedUserIds($relatedTo, $relatedId)
    {
        $ids = [];
        $mentions = Mention::find([
            'conditions' => 'relatedTo = :relatedTo: AND relatedId = :relatedId:',
            'bind' => ['relatedTo' => $relatedTo, 'relatedId' => $relatedId],
        ]);
        foreach ($mentions as $mention) {
            $ids[] = (string) $mention->userId;
        }

        return $ids;
    }

    /**
     * Notifies the mentioned user and marks the mention as notified.
     *
     * @param  Mention $mention The saved Mention row.
     * @return object|null The notification, or null when the target is not found.
     */
    public function notify($mention)
    {
        global $currentUser;

        if (empty($mention->userId) || ($currentUser && $currentUser->id === $mention->userId)) {
            return null;
        }

        $target = $this->resolveTarget($mention);
        if ($target === null) {
            return null;
        }

        $actorId = $currentUser ? $currentUser->id : $mention->createdUser;
        $actorName = $currentUser ? $currentUser->name : '';
        $project = !empty($mention->projectId) ? $this->load('Project', $mention->projectId) : null;

        $context = array_merge(
            [
                'userId' => $actorId,
                'userName' => $actorName,
                'relatedTo' => $mention->relatedTo,
            ],
            $target['context']
        );
        if ($project) {
            $context['projectShortcode'] = $project->shortCode;
        }

        $notification = $this->create([
            'description' => "{{User@{$actorId}}} mentioned you in {{{$target['token']}@{$target['id']}}}",
            'relatedTo' => $target['relatedTo'],
            'relatedId' => $target['id'],
            'context' => json_encode($context),
        ]);
        $this->recipientService->notifyUsers($notification, [(string) $mention->userId]);

        $mention->notified = true;
        $this->markNotified($mention);

        return $notification;
    }

    /**
     * Loads a record by id; overridden in tests.
     *
     * @param  string $model Model class name without namespace.
     * @param  string $id
     * @return mixed|false
     */
    protected function load($model, $id)
    {
        $class = 'Gaia\\MVC\\Models\\' . $model;

        return $class::findFirstById($id);
    }

    /**
     * Creates the system notification; overridden in tests.
     *
     * @param  array $data
     * @return object
     */
    protected function create(array $data)
    {
        return CreateModel::execute('systemnotification', $data);
    }

    /**
     * Saves the notified flag; overridden in tests.
     *
     * @param  Mention $mention
     * @return void
     */
    protected function markNotified($mention)
    {
        $mention->update();
    }

    /**
     * What the mention points at: the entity the notification links to and
     * the context that describes it.
     *
     * @param  Mention $mention
     * @return array|null Keys token, id, relatedTo, context.
     */
    protected function resolveTarget($mention)
    {
        switch ($mention->relatedTo) {
            case 'issue':
                return $this->issueTarget($this->load('Issue', $mention->relatedId), []);

            case 'comment':
                $comment = $this->load('Comment', $mention->relatedId);
                if (!$comment || $comment->relatedTo != 'conversationrooms') {
                    return null;
                }
                $room = $this->load('Conversationroom', $comment->relatedId);
                if (!$room || empty($room->issueId)) {
                    return null;
                }
                return $this->issueTarget($this->load('Issue', $room->issueId), ['commentId' => $comment->id]);

            case 'conversationroom':
                $room = $this->load('Conversationroom', $mention->relatedId);
                if (!$room) {
                    return null;
                }
                if (!empty($room->issueId)) {
                    return $this->issueTarget($this->load('Issue', $room->issueId), []);
                }
                return [
                    'token' => 'Conversationroom',
                    'id' => $room->id,
                    'relatedTo' => 'conversationroom',
                    'context' => ['conversationroomId' => $room->id],
                ];

            case 'idea':
                $idea = $this->load('Idea', $mention->relatedId);
                return $idea ? $this->ideaTarget($idea->id, []) : null;

            case 'ideamessage':
                $message = $this->load('Ideamessage', $mention->relatedId);
                return $message ? $this->ideaTarget($message->ideaId, ['ideamessageId' => $message->id]) : null;
        }

        return null;
    }

    /**
     * @param  Issue|false $issue
     * @param  array       $extra Extra context.
     * @return array|null
     */
    private function issueTarget($issue, array $extra)
    {
        if (!$issue) {
            return null;
        }

        return [
            'token' => 'Issue',
            'id' => $issue->id,
            'relatedTo' => 'issue',
            'context' => array_merge(['issueNumber' => $issue->issueNumber], $extra),
        ];
    }

    /**
     * @param  string $ideaId
     * @param  array  $extra Extra context.
     * @return array
     */
    private function ideaTarget($ideaId, array $extra)
    {
        return [
            'token' => 'Idea',
            'id' => $ideaId,
            'relatedTo' => 'idea',
            'context' => array_merge(['ideaId' => $ideaId], $extra),
        ];
    }
}
