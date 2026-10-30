<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * A workflow's statuses and what each allows: only a Draft or Disabled
 * workflow's graph can change, so a running workflow always has the graph
 * that was checked when it was activated.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowStatus
{
    public const DRAFT = 'Draft';
    public const ACTIVE = 'Active';
    public const DISABLED = 'Disabled';

    public const ALL = [self::DRAFT, self::ACTIVE, self::DISABLED];

    /**
     * Why the graph of a workflow in this status cannot change, or null when
     * it can. A null status means there is no such workflow.
     *
     * @param  string|null $status
     * @return string|null
     */
    public static function graphEditError($status)
    {
        if ($status === null) {
            return 'workflowId must be an existing workflow.';
        }
        if ($status === self::ACTIVE) {
            return 'An active workflow cannot be changed; disable it or set it back to Draft first.';
        }

        return null;
    }
}
