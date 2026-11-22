<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Issues\IssueGraph;
use Gaia\Libraries\Issues\ParentEdgeSource;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * An issue's parent can be neither the issue itself nor one of its
 * descendants, on create and when parentId changes. The check runs over
 * {@see IssueGraph} under its advisory lock, so the save must run in a
 * transaction (see Issue::save). The message never names the issues on the
 * path.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class issueParentCycleBehavior extends Behavior implements BehaviorInterface
{
    const PARENT_IS_SELF = 'An issue cannot be its own parent.';

    const PARENT_IS_DESCENDANT = 'The parent cannot be one of the issue\'s own sub-issues.';

    const PARENT_TOO_DEEP = 'The parent could not be checked: the issue hierarchy is too deep.';

    /**
     * @var IssueGraph|null
     */
    private $graph;

    /**
     * @param array           $options
     * @param IssueGraph|null $graph
     */
    public function __construct(array $options = [], $graph = null)
    {
        parent::__construct($options);
        $this->graph = $graph;
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
        return $this->check($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (is_array($snapshot)
            && (string) ($snapshot['parentId'] ?? '') === (string) $model->readAttribute('parentId')
        ) {
            return true;
        }

        return $this->check($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function check($model)
    {
        $parentId = (string) $model->readAttribute('parentId');
        $id = (string) $model->readAttribute('id');
        if ($parentId === '' || $id === '') {
            return true;
        }

        if ($parentId === $id) {
            return $this->refuse($model, self::PARENT_IS_SELF);
        }

        $graph = $this->graph($model);
        $graph->lock();
        $result = $graph->check($id, $parentId);
        if ($result === IssueGraph::CYCLE) {
            return $this->refuse($model, self::PARENT_IS_DESCENDANT);
        }
        if ($result === IssueGraph::TOO_DEEP) {
            return $this->refuse($model, self::PARENT_TOO_DEEP);
        }

        return true;
    }

    /**
     * @param  ModelInterface $model
     * @param  string         $message
     * @return bool
     */
    private function refuse($model, $message)
    {
        $model->appendMessage(new Message($message, 'parentId', 'InvalidValue'));
        return false;
    }

    /**
     * The graph over parent links, on the connection the issue saves through
     * so the lock joins the save's transaction.
     *
     * @param  ModelInterface $model
     * @return IssueGraph
     */
    private function graph($model)
    {
        if ($this->graph !== null) {
            return $this->graph;
        }

        return new IssueGraph(new ParentEdgeSource(), IssueGraph::DEFAULT_MAX_DEPTH, $model->getWriteConnection());
    }
}
