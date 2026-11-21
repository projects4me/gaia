<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Workspace;

/**
 * The rule for a workspace's short code (Workspaces D-012): required, lower
 * case letters and digits starting with a letter (the format of project short
 * codes), at most 50 characters, and unique among the workspaces that are not
 * deleted.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceShortCode
{
    const MAX_LENGTH = 50;

    const PATTERN = '/^[a-z][a-z0-9]*$/';

    /**
     * The base of a derived code when the source has no usable letters.
     */
    const FALLBACK = 'ws';

    const REQUIRED = 'A workspace needs a short code.';

    const INVALID = 'shortCode must start with a letter and hold only letters and digits, at most 50 characters.';

    const TAKEN = 'Another workspace already uses this short code.';

    /**
     * The stored form of a code: trimmed and in lower case.
     *
     * @param  mixed $code
     * @return string
     */
    public function normalize($code)
    {
        return strtolower(trim((string) $code));
    }

    /**
     * Whether a save sets or changes the code. A new record always does.
     *
     * @param  array|null $snapshot The stored row, null for a new record
     * @param  string     $code     The normalized value being saved
     * @return bool
     */
    public function isChange($snapshot, $code)
    {
        if (!is_array($snapshot)) {
            return true;
        }

        return $this->normalize($snapshot['shortCode'] ?? '') !== $code;
    }

    /**
     * Why a normalized code is not well formed, or null when it is.
     *
     * @param  string $code
     * @return string|null
     */
    public function formatError($code)
    {
        if ($code === '') {
            return self::REQUIRED;
        }
        if (strlen($code) > self::MAX_LENGTH || !preg_match(self::PATTERN, $code)) {
            return self::INVALID;
        }

        return null;
    }

    /**
     * Whether a workspace that is not deleted, other than the one being
     * saved, already uses the code.
     *
     * @param  string      $code
     * @param  string|null $exceptId
     * @return bool
     */
    public function isTaken($code, $exceptId = null)
    {
        $conditions = 'shortCode = :code: AND deleted = false';
        $bind = ['code' => $code];
        if ($exceptId !== null && (string) $exceptId !== '') {
            $conditions .= ' AND id <> :id:';
            $bind['id'] = (string) $exceptId;
        }

        return Workspace::count(['conditions' => $conditions, 'bind' => $bind]) > 0;
    }

    /**
     * A free, well-formed code derived from a wanted one (e.g. the code of
     * the project a workspace is created for): the wanted code when it is
     * free, otherwise the first free one with a number appended.
     *
     * @param  mixed $wanted
     * @return string
     */
    public function freeCodeFrom($wanted)
    {
        $base = preg_replace('/[^a-z0-9]/', '', $this->normalize($wanted));
        $base = ltrim($base, '0123456789');
        if ($base === '') {
            $base = self::FALLBACK;
        }
        $base = substr($base, 0, self::MAX_LENGTH);

        $code = $base;
        for ($number = 2; $this->isTaken($code); $number++) {
            $suffix = (string) $number;
            $code = substr($base, 0, self::MAX_LENGTH - strlen($suffix)) . $suffix;
        }

        return $code;
    }
}
