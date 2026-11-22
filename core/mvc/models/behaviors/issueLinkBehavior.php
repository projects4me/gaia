<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Issues\IssueGraph;
use Gaia\Libraries\Issues\IssueLinkRules;
use Gaia\Libraries\Issues\IssueLinks;
use Gaia\Libraries\Issues\LinkEdgeSource;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Guards an issue link (Workspaces D-021). On create it takes the issue
 * graph lock, checks {@see IssueLinkRules} and sets projectId and
 * targetProjectId from the two issues, whatever the request sent. On update
 * the type and the issues cannot change and the project ids keep their
 * stored values. The save must run in a transaction (see Issuelink::save).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class issueLinkBehavior extends Behavior implements BehaviorInterface
{
    const IMMUTABLE = 'A link\'s type and issues can\'t be changed: delete the link and create a new one.';

    const IMMUTABLE_FIELDS = ['sourceIssueId', 'targetIssueId', 'type'];

    const DERIVED_FIELDS = ['projectId', 'targetProjectId'];

    /**
     * @var IssueLinkRules|null
     */
    private $rules;

    /**
     * @var IssueGraph|null The graph whose lock is taken
     */
    private $lockGraph;

    /**
     * @param array               $options
     * @param IssueLinkRules|null $rules
     * @param IssueGraph|null     $lockGraph
     */
    public function __construct(array $options = [], $rules = null, $lockGraph = null)
    {
        parent::__construct($options);
        $this->rules = $rules;
        $this->lockGraph = $lockGraph;
    }

    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        $source = (string) $model->readAttribute('sourceIssueId');
        $target = (string) $model->readAttribute('targetIssueId');
        $type = (string) $model->readAttribute('type');
        if ($source === '' || $target === '' || $type === '') {
            // Required-field validation reports the missing value.
            return true;
        }

        $this->lockGraph($model)->lock();
        $result = $this->rules($model)->check($source, $target, $type);
        if ($result['error'] !== null) {
            $model->appendMessage(new Message($result['error'], 'targetIssueId', 'InvalidValue'));
            return false;
        }

        $model->writeAttribute('projectId', $result['projectId']);
        $model->writeAttribute('targetProjectId', $result['targetProjectId']);

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (!is_array($snapshot) || $snapshot === []) {
            return true;
        }

        foreach (self::IMMUTABLE_FIELDS as $field) {
            if ((string) ($snapshot[$field] ?? '') !== (string) $model->readAttribute($field)) {
                $model->appendMessage(new Message(self::IMMUTABLE, $field, 'InvalidValue'));
                return false;
            }
        }
        foreach (self::DERIVED_FIELDS as $field) {
            if (array_key_exists($field, $snapshot)) {
                $model->writeAttribute($field, $snapshot[$field]);
            }
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @return IssueLinkRules
     */
    private function rules($model)
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $connection = $model->getWriteConnection();

        return new IssueLinkRules(new IssueLinks(), function ($type) use ($connection) {
            return new IssueGraph(new LinkEdgeSource($type), IssueGraph::DEFAULT_MAX_DEPTH, $connection);
        });
    }

    /**
     * Any graph shares the one lock key; this one is on the connection the
     * link saves through, so the lock joins the save's transaction.
     *
     * @param  ModelInterface $model
     * @return IssueGraph
     */
    private function lockGraph($model)
    {
        if ($this->lockGraph !== null) {
            return $this->lockGraph;
        }

        return new IssueGraph(
            new LinkEdgeSource(IssueLinkRules::BLOCKS),
            IssueGraph::DEFAULT_MAX_DEPTH,
            $model->getWriteConnection()
        );
    }
}
