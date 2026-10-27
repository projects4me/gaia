<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Common;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Utils\TransactionScope;
use Gaia\Libraries\Utils\Util;
use Gaia\Libraries\Utils\ValueLabelRules;
use Gaia\Libraries\Utils\ValueLabels;

/**
 * Base controller of a list whose values have translated labels (issue
 * statuses, issue types).
 *
 * A record is saved with its labels in one transaction: create and update
 * accept `translations` (locale => label) with the other attributes, the
 * en-us label is required on create, and every read returns the labels as
 * `translations`. The value (`name`) is the key of the labels (see
 * ValueLabels), so it cannot be changed once created; records with the same
 * value share their labels, and labels of a value an active system record
 * uses need the system-record resource (acl.systemRecords). PATCH on the
 * collection does not save labels.
 *
 * The labels are written by the resource's component (ValueLabelsComponent),
 * listed in `$uses`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
abstract class ValueLabelController extends RestController
{
    /**
     * @var ValueLabels|null
     */
    protected $valueLabels = null;

    /**
     * @var ValueLabelRules|null
     */
    protected $rules = null;

    /**
     * The labels of the values this controller saves and reads.
     *
     * @return ValueLabels
     */
    abstract protected function createValueLabels();

    /**
     * Create a record with its labels; the value and labels must follow
     * ValueLabelRules.
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        return $this->rejectInvalidAttributes()
            ?: $this->rejectInvalidValue()
            ?: $this->rejectInvalidTranslations(true)
            ?: $this->assertSharedLabelWrite($this->requestAttributes()['name'])
            ?: $this->inTransaction(function () {
                return parent::postAction();
            });
    }

    /**
     * Update a record and the labels sent with it; the value cannot change.
     * The record updated is the one in the body (RestController::patchAction),
     * so that is the one checked.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        $attributes = $this->requestAttributes();
        $recordId = isset($attributes['id']) ? $attributes['id'] : $this->requestedId();

        return $this->rejectConflictingIds($attributes)
            ?: $this->rejectInvalidAttributes()
            ?: $this->rejectRename($recordId, $attributes)
            ?: $this->rejectInvalidTranslations(false)
            ?: $this->assertSharedLabelWrite($this->valueLabels()->valueOf($recordId))
            ?: $this->inTransaction(function () {
                return parent::patchAction();
            });
    }

    /**
     * Update several records; none of their values may change, and labels
     * are saved one record at a time (PATCH /{resource}/{id}).
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        $body = $this->request->getJsonRawBody(true);
        $collection = is_array($body) && isset($body['collection']) && is_array($body['collection'])
            ? $body['collection']
            : [];

        if (is_array($body) && array_key_exists('translations', $body)) {
            return $this->unprocessable('Labels are saved one ' . $this->valueLabels()->noun() . ' at a time.');
        }

        foreach ($collection as $record) {
            if (is_array($record) && array_key_exists('translations', $record)) {
                return $this->unprocessable('Labels are saved one ' . $this->valueLabels()->noun() . ' at a time.');
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
     * Delete a record together with the labels it leaves unused.
     *
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        return $this->inTransaction(function () {
            return parent::deleteAction();
        });
    }

    /**
     * The labels sent with the record (`translations`), or null when none were sent.
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
     * The labels of the records this request saves or reads.
     *
     * @return ValueLabels
     */
    public function valueLabels()
    {
        if ($this->valueLabels === null) {
            $this->valueLabels = $this->createValueLabels();
        }

        return $this->valueLabels;
    }

    /**
     * Return a 422 response when an attribute other than the value and labels
     * is invalid; none by default.
     *
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectInvalidAttributes()
    {
        return null;
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
            $this->valueLabels()->forgetWrittenLocales();
        }
    }

    /**
     * Add `translations` to every record in the response.
     *
     * @param  array $data
     * @return \Phalcon\Http\Response
     */
    protected function returnResponse(array $data)
    {
        return parent::returnResponse($this->mapResources($data, [$this, 'addTranslations']));
    }

    /**
     * Apply $map to the resources of a response: the one resource of a single
     * read, or the list of a collection read.
     *
     * @param  array    $data
     * @param  callable $map  Given the resources, returns them changed.
     * @return array
     */
    protected function mapResources(array $data, callable $map)
    {
        if (empty($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        $single = isset($data['data']['attributes']);
        $resources = $map($single ? [$data['data']] : array_filter($data['data'], 'is_array'));
        $data['data'] = $single ? reset($resources) : array_replace($data['data'], $resources);

        return $data;
    }

    /**
     * Set `translations` (locale => label) on each resource that has its value.
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

        $labels = $this->valueLabels()->labelsFor($values);
        foreach ($resources as $index => $resource) {
            if (isset($resource['attributes']['name']) && is_string($resource['attributes']['name'])) {
                $name = $resource['attributes']['name'];
                $resources[$index]['attributes']['translations'] = isset($labels[$name]) ? $labels[$name] : (object) [];
            }
        }

        return $resources;
    }

    /**
     * Return a 422 response when `attributes.id` and `data.id` name different
     * records: RestController::patchAction saves `data.id`, so the checks
     * here must run on that same record.
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
     * Return a 422 response when the new record's value breaks ValueLabelRules.
     *
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectInvalidValue()
    {
        $attributes = $this->requestAttributes();
        $error = $this->rules()->valueError(isset($attributes['name']) ? $attributes['name'] : null);

        return $error === null ? null : $this->unprocessable($error);
    }

    /**
     * Return a 422 response when an update changes the value, which keys the
     * labels.
     *
     * @param  mixed $recordId   Id of the record being updated
     * @param  array $attributes The attributes the update sends
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectRename($recordId, array $attributes)
    {
        if (!array_key_exists('name', $attributes)) {
            return null;
        }

        $value = $this->valueLabels()->valueOf($recordId);
        $error = $value === null ? null : $this->rules()->valueChangeError($value, $attributes['name']);

        return $error === null ? null : $this->unprocessable($error);
    }

    /**
     * Require the system-record resource (e.g. setting.update) to send labels
     * for a value a system record uses: labels are shared by value, so a
     * project record's labels would otherwise change the system record's
     * labels too.
     *
     * @param  mixed $value Value of the record the labels are saved for
     * @return null
     * @throws \Gaia\Exception\Access
     */
    protected function assertSharedLabelWrite($value)
    {
        if ($this->requestedTranslations() === null || !is_string($value) || !$this->getDI()->has('acl')) {
            return null;
        }

        if ($this->valueLabels()->isSystemValue($value)) {
            $metadata = $this->getDI()->get('metaManager')->getModelMeta($this->valueLabels()->modelName());
            $this->getDI()->get('acl')->checkAccess((string) $metadata['acl']['systemRecords']['resource']);
        }

        return null;
    }

    /**
     * Return a 422 response when the labels sent break ValueLabelRules.
     *
     * @param  bool $creating
     * @return \Phalcon\Http\Response|null
     */
    protected function rejectInvalidTranslations($creating)
    {
        $attributes = $this->requestAttributes();
        $translations = isset($attributes['translations']) ? $attributes['translations'] : null;
        $error = $this->rules()->translationsError($translations, $creating);

        return $error === null ? null : $this->unprocessable($error);
    }

    /**
     * @return ValueLabelRules
     */
    protected function rules()
    {
        if ($this->rules === null) {
            $this->rules = ValueLabelRules::fromConfig($this->valueLabels()->noun());
        }

        return $this->rules;
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
