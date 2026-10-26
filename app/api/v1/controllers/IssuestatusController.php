<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Utils\IssueStatusLabels;
use Gaia\Libraries\Utils\IssueStatusRules;
use Gaia\Libraries\Utils\TransactionScope;
use Gaia\Libraries\Utils\Util;
use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Issuestatus;

/**
 * Issue statuses controller
 *
 * Statuses are managed in Settings > Issue Statuses. Writes to system statuses
 * need setting.update (Issuestatus metadata, acl.systemRecords). This
 * controller adds what the Settings page needs on top of the REST defaults:
 * colours limited to the status palette, no deleting a status that issues
 * still use, and an opt-in issue count per status (`?issueCount=true`).
 *
 * A status is saved with its translated labels in one transaction: create and
 * update accept `translations` (locale => label) with the other attributes,
 * the en-us label is required on create, and every status read returns its
 * labels as `translations`. The status value (`name`) is the key of the labels
 * (see IssueStatusLabels), so it cannot be changed once created; statuses with
 * the same value share their labels. PATCH on the collection does not save
 * labels.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssuestatusController extends RestController
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
     * @var IssueStatusLabels|null
     */
    protected $statusLabels = null;

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
     * Create a status with its labels; the colour must come from the palette
     * and the value and labels must follow IssueStatusRules.
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        return $this->rejectInvalidColor()
            ?: $this->rejectInvalidValue()
            ?: $this->rejectInvalidTranslations(true)
            ?: $this->assertSharedLabelWrite($this->requestAttributes()['name'])
            ?: $this->inTransaction(function () {
                return parent::postAction();
            });
    }

    /**
     * Update a status and the labels sent with it; the colour must come from
     * the palette and the value cannot change. The status updated is the one
     * in the body (RestController::patchAction), so that is the one checked.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        $attributes = $this->requestAttributes();
        $statusId = isset($attributes['id']) ? $attributes['id'] : $this->requestedId();

        return $this->rejectConflictingIds($attributes)
            ?: $this->rejectInvalidColor()
            ?: $this->rejectRename($statusId, $attributes)
            ?: $this->rejectInvalidTranslations(false)
            ?: $this->assertSharedLabelWrite($this->statusValue($statusId))
            ?: $this->inTransaction(function () {
                return parent::patchAction();
            });
    }

    /**
     * Update several statuses; none of their values may change, and labels
     * are saved one status at a time (PATCH /issuestatus/{id}).
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        $body = $this->request->getJsonRawBody(true);
        $collection = is_array($body) && isset($body['collection']) && is_array($body['collection'])
            ? $body['collection']
            : [];

        foreach ($collection as $record) {
            if (is_array($record) && array_key_exists('translations', $record)) {
                return $this->unprocessable('Labels are saved one status at a time.');
            }
            if (is_array($record) && isset($record['id'])) {
                $rejected = $this->rejectRename($record['id'], $record);
                if ($rejected) {
                    return $rejected;
                }
            }
        }

        return parent::patchCollectionAction();
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

        // The status and the labels it leaves unused are deleted together.
        return $this->inTransaction(function () {
            return parent::deleteAction();
        });
    }

    /**
     * The labels sent with the status (`translations`), or null when none were sent.
     *
     * @return array|null
     */
    public function requestedTranslations()
    {
        $attributes = $this->requestAttributes();

        return isset($attributes['translations']) && is_array($attributes['translations'])
            ? $attributes['translations']
            : null;
    }

    /**
     * The labels of the statuses this request saves or reads.
     *
     * @return IssueStatusLabels
     */
    public function statusLabels()
    {
        if ($this->statusLabels === null) {
            $this->statusLabels = new IssueStatusLabels();
        }

        return $this->statusLabels;
    }

    /**
     * Run a write action in one transaction with the labels its components
     * save, committing only a 2xx response. The written locales' caches are
     * dropped once the transaction is over.
     *
     * @param  callable $action Returns the response.
     * @return \Phalcon\Http\Response
     */
    protected function inTransaction(callable $action)
    {
        $scope = new TransactionScope($this->di->get('db'));

        try {
            return $scope->run($action, function ($response) {
                $statusCode = (int) ($response->getStatusCode() ?: 200);
                return $statusCode >= 200 && $statusCode < 300;
            });
        } finally {
            $this->statusLabels()->forgetWrittenLocales();
        }
    }

    /**
     * Add `translations` to every status in the response, and `issueCount`
     * when it was requested.
     *
     * @param  array $data
     * @return \Phalcon\Http\Response
     */
    protected function returnResponse(array $data)
    {
        if (!empty($data['data']) && is_array($data['data'])) {
            $single = isset($data['data']['attributes']);
            $resources = $single ? [$data['data']] : array_filter($data['data'], 'is_array');

            $resources = $this->addTranslations($resources);
            if ($this->issueCounts !== null) {
                $resources = array_map([$this, 'addIssueCount'], $resources);
            }

            $data['data'] = $single ? reset($resources) : array_replace($data['data'], $resources);
        }

        return parent::returnResponse($data);
    }

    /**
     * Set `translations` (locale => label) on each status resource that has its value.
     *
     * @param  array $resources
     * @return array
     */
    protected function addTranslations(array $resources)
    {
        $values = [];
        foreach ($resources as $resource) {
            if (isset($resource['attributes']['name']) && is_string($resource['attributes']['name'])) {
                $values[] = $resource['attributes']['name'];
            }
        }
        if (empty($values)) {
            return $resources;
        }

        $labels = $this->statusLabels()->labelsFor($values);
        foreach ($resources as $index => $resource) {
            if (isset($resource['attributes']['name']) && is_string($resource['attributes']['name'])) {
                $name = $resource['attributes']['name'];
                $resources[$index]['attributes']['translations'] = isset($labels[$name]) ? $labels[$name] : (object) [];
            }
        }

        return $resources;
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

    /**
     * Return a 422 response when `attributes.id` and `data.id` name different
     * statuses: RestController::patchAction saves `data.id`, so the checks
     * here must run on that same status.
     *
     * @param  array $attributes
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectConflictingIds(array $attributes)
    {
        $body = $this->request->getJsonRawBody(true);
        if (!isset($attributes['id']) || empty($body['data']['id'])) {
            return null;
        }

        return (string) $attributes['id'] === (string) $body['data']['id']
            ? null
            : $this->unprocessable('attributes.id must match data.id.');
    }

    /**
     * Return a 422 response when the payload sets a colour outside the palette.
     *
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectInvalidColor()
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
     * Return a 422 response when the new status's value breaks IssueStatusRules.
     *
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectInvalidValue()
    {
        $attributes = $this->requestAttributes();
        $error = IssueStatusRules::fromConfig()->valueError(isset($attributes['name']) ? $attributes['name'] : null);

        return $error === null ? null : $this->unprocessable($error);
    }

    /**
     * Return a 422 response when an update changes the status value, which
     * keys the status labels.
     *
     * @param  mixed $statusId   Id of the status being updated
     * @param  array $attributes The attributes the update sends
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectRename($statusId, array $attributes)
    {
        if (!array_key_exists('name', $attributes) || !is_scalar($statusId)) {
            return null;
        }

        $status = Issuestatus::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => (string) $statusId],
        ]);
        $error = $status ? IssueStatusRules::fromConfig()->valueChangeError($status->name, $attributes['name']) : null;

        return $error === null ? null : $this->unprocessable($error);
    }

    /**
     * Require the system-record resource (setting.update) to send labels for a
     * value a system status uses: labels are shared by value, so a project
     * status's labels would otherwise change the system status's labels too.
     *
     * @param  mixed $value Value of the status the labels are saved for
     * @return null
     * @throws \Gaia\Exception\Access
     */
    protected function assertSharedLabelWrite($value)
    {
        if ($this->requestedTranslations() === null || !is_string($value) || !$this->getDI()->has('acl')) {
            return null;
        }

        if ($this->statusLabels()->isSystemValue($value)) {
            $metadata = $this->getDI()->get('metaManager')->getModelMeta('Issuestatus');
            $this->getDI()->get('acl')->checkAccess((string) $metadata['acl']['systemRecords']['resource']);
        }

        return null;
    }

    /**
     * The value of a stored status, or null when there is none.
     *
     * @param  mixed $statusId
     * @return string|null
     */
    protected function statusValue($statusId)
    {
        if (!is_scalar($statusId)) {
            return null;
        }

        $status = Issuestatus::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => (string) $statusId],
        ]);

        return $status ? (string) $status->name : null;
    }

    /**
     * Return a 422 response when the labels sent break IssueStatusRules.
     *
     * @param  bool $creating
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectInvalidTranslations($creating)
    {
        $attributes = $this->requestAttributes();
        $translations = isset($attributes['translations']) ? $attributes['translations'] : null;
        $error = IssueStatusRules::fromConfig()->translationsError($translations, $creating);

        return $error === null ? null : $this->unprocessable($error);
    }

    /**
     * The id in the request body (JSON:API `data.id`), else the one in the URL.
     *
     * @return mixed
     */
    protected function requestedId()
    {
        $body = $this->request->getJsonRawBody(true);

        return isset($body['data']['id']) ? $body['data']['id'] : $this->id;
    }

    /**
     * Read the attributes from the JSON:API request body.
     *
     * @return array
     */
    protected function requestAttributes()
    {
        $util = new Util();
        $body = $util->objectToArray($this->request->getJsonRawBody());

        return isset($body['data']['attributes']) && is_array($body['data']['attributes'])
            ? $body['data']['attributes']
            : (is_array($body) ? $body : []);
    }

    /**
     * Build a 422 response with one message.
     *
     * @param  string $message
     * @return \Phalcon\Http\Response
     */
    protected function unprocessable($message)
    {
        $this->response->setStatusCode(422, 'Unprocessable Entity');
        $this->response->setJsonContent([
            'status' => 'ERROR',
            'messages' => [$message],
        ]);
        return $this->response;
    }
}
