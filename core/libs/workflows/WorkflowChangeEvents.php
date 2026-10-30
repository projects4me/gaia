<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Raises the trigger events of a record a workflow run created or changed,
 * so other workflows can start from it. The run's own writes don't pass
 * through a controller, so nothing else raises them.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface WorkflowChangeEvents
{
    /**
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @param  string                      $on  TriggerEventCatalog::ON_CREATE or ON_UPDATE.
     * @param  array                       $run What the events carry (see LoopGuard::carried).
     * @return void
     */
    public function changed($record, $on, array $run);
}
