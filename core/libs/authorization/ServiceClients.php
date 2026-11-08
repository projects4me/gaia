<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

/**
 * What happens to a service client's sign-in: its status values, the
 * last-used stamp when it gets a token, and cutting off its tokens when it is
 * disabled.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceClients
{
    /**
     * The client may get tokens.
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * The client gets no tokens.
     */
    public const STATUS_DISABLED = 'disabled';

    /**
     * Every status a service client may have.
     */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_DISABLED];

    /**
     * Stamp the client as used now.
     *
     * A bound UPDATE, not a model save: a usage stamp is not an edit, so it
     * leaves the audit trail and modifiedUser alone (there is no current user
     * while a token is issued). A failed stamp does not stop the sign-in.
     *
     * @param  string $clientId
     * @return bool Whether the stamp was written
     */
    public function recordUse($clientId)
    {
        try {
            return (bool) $this->db()->execute(
                'UPDATE serviceclients SET "lastUsedAt" = ? WHERE "clientId" = ? AND deleted = false',
                [gmdate('Y-m-d H:i:s'), (string) $clientId]
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete every access token issued to the client, so a disabled client's
     * tokens stop working at once.
     *
     * @param  string $clientId
     * @return void
     */
    public function revokeTokens($clientId)
    {
        \Phalcon\Di::getDefault()->get('modelsManager')->executeQuery(
            'DELETE FROM [Gaia\MVC\Models\Oauthaccesstoken] WHERE client_id = :clientId:',
            ['clientId' => (string) $clientId]
        );
    }

    /**
     * The database connection the usage stamp writes through.
     *
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    protected function db()
    {
        return \Phalcon\Di::getDefault()->getShared('db');
    }
}
