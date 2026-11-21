<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

/**
 * A workspace delete that is refused because the workspace still owns things.
 * The code is the HTTP status (409); the counts say how many of each kind.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceDeleteRefusal extends \RuntimeException
{
    /**
     * @var array
     */
    protected $counts;

    /**
     * @param string $message
     * @param int    $code
     * @param array  $counts label => count
     */
    public function __construct($message, $code = 409, array $counts = [])
    {
        parent::__construct($message, $code);
        $this->counts = $counts;
    }

    /**
     * @return array label => count
     */
    public function getCounts()
    {
        return $this->counts;
    }
}
