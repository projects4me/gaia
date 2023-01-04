<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\Model\MetaData;
use Gaia\Libraries\Utils\Util;
use Gaia\Core\MVC\Models\Query;

/**
 * This class is the base model in the the application and is used to
 * overwrite the default functionality of Phalcon\Mvc\Model in order to
 * introduce manual meta-data extensions along with other changes
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation\Mvc
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Model extends PhalconModel
{
    /**
     * This is the model metadata
     *
     * @var $metadata
     * @type array
     */
    protected $metadata;

    /**
     * The alias of the current model
     *
     * @var $modelAlias
     */
    public $modelAlias;

    /**
     * This flag maintains if the model was changed.
     *
     * @var $isChanged
     */
    public $isChanged = false;

    /**
     * The audit information of a change was made in th model
     *
     * @var $audit
     */
    public $audit;

    /**
     * This is the query for this model, we are using this to allow behaviors to make changes if required.
     *
     * @var \Gaia\Core\MVC\Models\Query $query
     */
    public $query;

    /**
     * This is the new id that is being inserted in the system.
     * 
     * @var $newId
     */
    public $newId;

    /**
     * This function is used in order to load the different behaviors that this model is
     * set to use.
     *
     * @return void
     */
    public function loadBehavior()
    {
        // Load each of the relationship types one by one
        if (isset($this->metadata['behaviors']) && !empty($this->metadata['behaviors'])) {
            foreach ($this->metadata['behaviors'] as $behavior) {
                $behaviorClass = '\\Gaia\\MVC\\Models\\Behaviors\\' . $behavior;
                $this->addBehavior(new $behaviorClass);
            }
        }
    }

    /**
     * This function read the meta data stored for a model and returns an array
     * with parsed in a format that PhalconModel can understand
     *
     * @return array
     */
    public function metaData()
    {
        return $this->metadata;
    }

    /**
     * For some reason the tableName being set in the function metaData gets
     * overridden so we are setting the table again when the object is being
     * constructed
     *
     * @return void
     */
    public function onConstruct()
    {
        $modelName = $this->getModelName();
        $this->modelAlias = $modelName;

        $metadata = $this->getDI()->get('metaManager')->getModelMeta($modelName);
        $this->setSource($metadata['tableName']);
        $this->metadata = $metadata;

        // Load the behaviors in the model as well
        $this->loadBehavior();

        $this->keepSnapshots(true);
    }

    /**
     * This function returns model name.
     *
     * @return string
     */
    public function getModelName()
    {
        return Util::classWithoutNamespace(get_class($this));
    }

    /**
     * This function is an alternate of \Phalcon\Mvc\Model::find
     * This RestController must use this function instead of find so that we can
     * support the default pagination, sorting, filtering and relationships
     *
     * @param array $params
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     * @throws \Phalcon\Exception
     * @todo remove the relationship preload to all
     */
    public function read(array $params)
    {
        $this->query = $this->getQuery();
        
        $this->fireEvent("beforeQuery");
        $this->query->prepareReadQuery($this->getModelPath(), $params);

        $this->fireEvent("afterQuery");
        
        $phalconQuery = $this->query->getPhalconQuery();
        $data = $phalconQuery->execute();

        return $data;
    }

    /**
     * This function is an alternate of \Phalcon\Mv\Model::find
     * This RestController must use this function instead of find so that we can
     * support the default pagination, sorting, filtering and relationships
     *
     * @param array $params
     * @return array
     * @throws \Phalcon\Exception
     */
    public function readAll(array $params)
    {
        $this->fireEvent("beforeRead");
        
        $this->query = $this->getQuery();
        $this->fireEvent("beforeQuery");

        $this->query->prepareReadAllQuery($this->getModelPath(), $params);
        $this->fireEvent("afterQuery");

        $phalconQuery = $this->query->getPhalconQuery();
        $data = $phalconQuery->execute();

        return $data;
    }

    /**
     * This function is an alternate of \Phalcon\Mv\Model::getRelated
     * This RestController must use this function instead of find so that we can
     * support the default pagination, sorting, filtering and relationships
     *
     * @param array $params
     * @return array
     * @throws \Phalcon\Exception
     */
    public function readRelated(array $params)
    {
        $related = $params['related'];
        $params['rels'] = array($related);

        // Set the fields
        if (isset($params['fields']) && !empty($params['fields'])) {
            $params['fields'] = $params['fields'];
        } else {
            $params['fields'] = $related . '.*';
        }

        $this->query = $this->getQuery();
        $this->fireEvent("beforeQuery");
        $this->query->prepareReadQuery($this->getModelPath(), $params);
        $this->fireEvent("afterQuery");
        $phalconQuery = $this->query->getPhalconQuery();
        $data = $phalconQuery->execute();

        return $data;
    }

    /**
     * This function return path of current model.
     *
     * @return string
     */
    public function getModelPath()
    {
        return get_class($this);
    }

    /**
     * This function return new Query object.
     *
     * @return \Gaia\Core\MVC\Models\Query
     */
    public function getQuery()
    {
        return new Query($this->getDI(), $this->modelAlias);
    }
}
