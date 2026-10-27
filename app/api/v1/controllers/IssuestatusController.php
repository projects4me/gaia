<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Libraries\Utils\ValueLabels;
use Gaia\MVC\Models\Issue;
use Gaia\MVC\REST\Controllers\Common\ValueLabelController;

/**
 * Issue statuses controller
 *
 * Statuses are managed in Settings > Issue Statuses. Writes to system statuses
 * need setting.update (Issuestatus metadata, acl.systemRecords). This
 * controller adds what the Settings page needs on top of the REST defaults:
 * colours limited to the status palette, no deleting a status that issues
 * still use, and an opt-in issue count per status (`?issueCount=true`).
 *
 * A status is saved with its translated labels in one transaction, and the
 * status value (`name`) cannot change once created (see ValueLabelController).
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssuestatusController extends ValueLabelController
{
    /**
     * Colours a status may use (the palette Prometheus has used for statuses).
     *
     * @var array
     */
    public const COLORS = ['#80b5d3', '#b3244f', '#f7bf65', '#5ac594', '#e98a7e'];

    public $uses = ['Issuestatus'];

    /**
     * Issue counts by status id, loaded when the request asks for them.
     *
     * @var array|null
     */
    protected $issueCounts = null;

    /**
     * List statuses, with issue counts when `issueCount=true`.
     *
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        $this->loadIssueCountsIfRequested();
        return parent::listAction();
    }

    /**
     * Get one status, with its issue count when `issueCount=true`.
     *
     * @return \Phalcon\Http\Response
     */
    public function getAction()
    {
        $this->loadIssueCountsIfRequested();
        return parent::getAction();
    }

    /**
     * Delete a status unless issues still use it.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\Permission
     */
    public function deleteAction()
    {
        // Authorize first so a user without access gets 403, not the in-use message.
        if ($this->getDI()->has('acl')) {
            $acl = $this->getDI()->get('acl');
            $acl->assertRecordIdInScope('Issuestatus', $this->id);
            $acl->assertSystemRecordWrite('Issuestatus', [], $this->id);
        }

        $inUse = Issue::count([
            'conditions' => 'statusId = :statusId: AND deleted = 0',
            'bind' => ['statusId' => (string) $this->id],
        ]);
        if ($inUse > 0) {
            throw new \Gaia\Exception\Permission(
                "This status cannot be deleted: {$inUse} issue(s) use it.",
                null,
                null,
                ['suggestion' => 'Move those issues to another status first.']
            );
        }

        return parent::deleteAction();
    }

    /**
     * @return ValueLabels
     */
    protected function createValueLabels()
    {
        return ValueLabels::forIssueStatuses();
    }

    /**
     * Return a 422 response when the payload sets a colour outside the palette.
     *
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectInvalidAttributes()
    {
        $attributes = $this->requestAttributes();

        if (!array_key_exists('color', $attributes) || $attributes['color'] === null) {
            return null;
        }

        if (in_array($attributes['color'], self::COLORS, true)) {
            return null;
        }

        return $this->unprocessable('Colour must be one of: ' . implode(', ', self::COLORS) . '.');
    }

    /**
     * Add `issueCount` to every status in the response when it was requested
     * (the base adds `translations`).
     *
     * @param  array $data
     * @return \Phalcon\Http\Response
     */
    protected function returnResponse(array $data)
    {
        if ($this->issueCounts !== null) {
            $data = $this->mapResources($data, function (array $resources) {
                return array_map([$this, 'addIssueCount'], $resources);
            });
        }

        return parent::returnResponse($data);
    }

    /**
     * Load issue counts per status when `issueCount=true`. Counts cover every
     * project, so they need setting.get (the Settings page reads them).
     *
     * @return void
     * @throws \Gaia\Exception\Access
     */
    protected function loadIssueCountsIfRequested()
    {
        if (!filter_var($this->request->get('issueCount', null, false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ($this->getDI()->has('acl')) {
            $this->getDI()->get('acl')->checkAccess('setting.get');
        }

        $this->issueCounts = [];
        $rows = Issue::count([
            'conditions' => 'deleted = 0',
            'group' => 'statusId',
        ]);
        foreach ($rows as $row) {
            $this->issueCounts[(string) $row->statusId] = (int) $row->rowcount;
        }
    }

    /**
     * Set `issueCount` on one status resource.
     *
     * @param  array $resource
     * @return array
     */
    protected function addIssueCount(array $resource)
    {
        if (isset($resource['id'], $resource['attributes']) && is_array($resource['attributes'])) {
            $id = (string) $resource['id'];
            $resource['attributes']['issueCount'] = isset($this->issueCounts[$id]) ? $this->issueCounts[$id] : 0;
        }

        return $resource;
    }
}
