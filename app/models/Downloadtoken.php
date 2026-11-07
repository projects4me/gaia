<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Downloadtoken Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Downloadtoken extends Model
{
    /**
     * Consume a single-use token: returns the token only to the one caller whose
     * conditional DELETE removes the row, so concurrent requests cannot both win.
     * Expired tokens are removed and treated as missing.
     *
     * @param string $token the download token value
     * @return Downloadtoken|null the consumed token, or null when missing, expired or already consumed
     */
    public static function consume($token)
    {
        $downloadToken = self::findFirst([
            'conditions' => 'downloadToken = :token:',
            'bind' => ['token' => (string) $token],
        ]);
        if (!$downloadToken) {
            return null;
        }

        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute('DELETE FROM download_tokens WHERE "downloadToken" = ?', [(string) $token]);
        if ($db->affectedRows() < 1) {
            return null;
        }

        $expiry = new \DateTime($downloadToken->expires, new \DateTimeZone('UTC'));
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        return $now->getTimestamp() <= $expiry->getTimestamp() ? $downloadToken : null;
    }
}
