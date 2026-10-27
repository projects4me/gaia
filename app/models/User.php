<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\Util;

/**
 * User Model
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class User extends Model
{
    /**
     * `users.type` of a person.
     *
     * @var string
     */
    public const TYPE_HUMAN = 'Human';

    /**
     * `users.type` of an AI agent; such a user always has an agents row.
     *
     * @var string
     */
    public const TYPE_AGENT = 'Agent';

    /**
     * Flag decides whether to execute hasManyToMany relationship queries
     * separately or not.
     *
     * @var boolean
     */
    public $splitQueries = false;

    /**
     * Retrieves the first system user with Admin role from the database.
     *
     * @return \Gaia\MVC\Models\User|null Returns the first admin user found or null if none exists
     */
    public static function getSystemUser()
    {
        $di = \Phalcon\Di::getDefault();
        $builder = $di->get('modelsManager')->createBuilder();
        $builder->from(['User' => 'Gaia\MVC\Models\User'])
            ->columns(['User.id','User.name'])
            ->leftJoin('Gaia\MVC\Models\Userrole', 'ur.userId = User.id', 'ur')
            ->leftJoin('Gaia\MVC\Models\Role', 'r.id = ur.roleId', 'r')
            ->where('r.name = :roleName:', ['roleName' => 'Admin'])
            ->limit(1);

        $result = $builder->getQuery()->execute()->getFirst();

        return User::findFirstById($result->id);
    }

    /**
     * Sets the current user in the global scope.
     *
     * @param \Gaia\MVC\Models\User $user The user instance to set as current user
     * @global \Gaia\MVC\Models\User $currentUser Global variable holding current user instance
     */
    public static function setCurrentUser(\Gaia\MVC\Models\User $user)
    {
        global $currentUser;
        $currentUser = $user;
    }

    /**
     * A new user without a type is a Human.
     *
     * @return void
     */
    public function beforeValidationOnCreate()
    {
        if (!isset($this->type) || (string) $this->type === '') {
            $this->type = self::TYPE_HUMAN;
        }
    }

    /**
     * Whether this user is an AI agent.
     *
     * @return bool
     */
    public function isAgent()
    {
        return isset($this->type) && strcasecmp((string) $this->type, self::TYPE_AGENT) === 0;
    }
}
