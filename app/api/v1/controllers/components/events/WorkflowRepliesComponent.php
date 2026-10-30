<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\Libraries\Workflows\ActorSteps;
use Gaia\Libraries\Workflows\UserReplies;
use Phalcon\Events\Event;

/**
 * Hands comments and issue reassignments to the workflow 'Ask a user'
 * steps waiting on them (see UserReplies). A failure here never fails the
 * user's write.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components\Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowRepliesComponent
{
    /**
     * @var UserReplies|null
     */
    protected $replies;

    /**
     * @param UserReplies|null $replies Built on first use when null.
     */
    public function __construct(UserReplies $replies = null)
    {
        $this->replies = $replies;
    }

    /**
     * A comment answers the steps that asked its author on the commented record.
     *
     * @param  Event  $event
     * @param  object $controller
     * @param  object $model
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        if (!self::filled($model, ['id', 'relatedTo', 'relatedId', 'createdUser'])) {
            return;
        }
        $this->safely(function () use ($model) {
            $this->replies()->commented(
                (string) $model->relatedTo,
                (string) $model->relatedId,
                (string) $model->createdUser,
                (string) $model->id,
                isset($model->comment) ? (string) $model->comment : ''
            );
        });
    }

    /**
     * A new assignee of an issue answers the steps waiting on it. Unassigning
     * it does not.
     *
     * @param  Event  $event
     * @param  object $controller
     * @param  object $model
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $audit = isset($model->audit) && is_array($model->audit) ? $model->audit : [];
        if (
            !is_object($model)
            || (new \ReflectionClass($model))->getShortName() !== 'Issue'
            || empty($audit['assignee']['new'])
            || !self::filled($model, ['id'])
        ) {
            return;
        }
        $this->safely(function () use ($model, $audit) {
            $this->replies()->reassigned('issue', (string) $model->id, (string) $audit['assignee']['new']);
        });
    }

    /**
     * @return UserReplies
     */
    protected function replies()
    {
        if ($this->replies === null) {
            $this->replies = new UserReplies(new ActorSteps(), (new WorkflowTriggersComponent())->runner());
        }

        return $this->replies;
    }

    /**
     * @param  callable $answer
     * @return void
     */
    private function safely(callable $answer)
    {
        global $logger;

        try {
            $answer();
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Workflow reply failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param  object   $model
     * @param  string[] $fields
     * @return bool
     */
    private static function filled($model, array $fields)
    {
        foreach ($fields as $field) {
            if (!isset($model->$field) || $model->$field === '') {
                return false;
            }
        }

        return true;
    }
}
