<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

/**
 * Save records as a given user when there is no signed-in user (an anonymous
 * enrollment request or poll): the user is the request's current user while
 * the work runs, so the records name them as creator or modifier, and the
 * previous current user is put back afterwards. A save that fails is
 * reported as a BadRequest with the model's messages.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActingUser
{
    /**
     * Run work with the given user as the current user.
     *
     * @param  \Gaia\MVC\Models\User|object|null $user Null keeps the current user
     * @param  callable                          $work
     * @return mixed
     */
    public function run($user, callable $work)
    {
        global $currentUser;

        $previous = $currentUser;
        if ($user) {
            $currentUser = $user;
        }
        try {
            return $work();
        } finally {
            $currentUser = $previous;
        }
    }

    /**
     * Save a model as the given user.
     *
     * @param  \Phalcon\Mvc\ModelInterface       $model
     * @param  \Gaia\MVC\Models\User|object|null $user
     * @param  string                            $failure Message when the model gives none
     * @return void
     * @throws \Gaia\Exception\BadRequest When the save fails
     */
    public function save($model, $user, $failure)
    {
        $saved = $this->run($user, function () use ($model) {
            return $model->save();
        });
        if ($saved) {
            return;
        }

        $messages = [];
        foreach ($model->getMessages() as $message) {
            $messages[] = $message->getMessage();
        }
        throw new \Gaia\Exception\BadRequest(implode(' ', $messages) ?: $failure);
    }
}
