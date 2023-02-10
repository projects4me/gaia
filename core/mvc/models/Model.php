<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Phalcon\Mvc\Model as PhalconModel;
use Gaia\Libraries\Utils\Util;
use Gaia\Core\MVC\Models\Query;
use Gaia\Core\MVC\Models\DataExtractor;
use Gaia\Core\MVC\Models\Query\Meta as QueryMeta;

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
     * @var array
     */
    protected $metadata;

    /**
     * The alias of the current model
     *
     * @var string
     */
    public $modelAlias;

    /**
     * This flag maintains if the model was changed.
     *
     * @var bool
     */
    public $isChanged = false;

    /**
     * The audit information of a change was made in th model
     *
     * @var
     */
    public $audit;

    /**
     * This is the query for this model, we are using this to allow behaviors to make changes if required.
     *
     * @var \Gaia\Core\MVC\Models\Query
     */
    public $query;

    /**
     * This is the new id that is being inserted in the system.
     *
     * @var string
     */
    public $newId;

    /**
     * This contains array of result on executing a query.
     *
     * @var array
     */
    protected $resultSets;

    /**
     * Flag decides whether to execute hasManyToMany relationship queries
     * separately or not.
     *
     * @var bool
     */
    protected $splitQueries = true;

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
        $modelName = Util::extractClassFromNamespace(get_class($this));
        $this->modelAlias = $modelName;

        $metadata = $this->getDI()->get('metaManager')->getModelMeta($modelName);
        $this->setSource($metadata['tableName']);
        $this->metadata = $metadata;

        // Load the behaviors in the model as well
        $this->loadBehavior();

        $this->keepSnapshots(true);
    }

    /**
     * This function is an alternate of \Phalcon\Mvc\Model::find
     * This RestController must use this function instead of find so that we can
     * support the default pagination, sorting, filtering and relationships
     *
     * @param array $params
     * @return array
     * @throws \Phalcon\Exception
     * @todo remove the relationship preload to all
     */
    public function read(array $params)
    {
        $this->instantiateQuery($params);

        $this->relationship->setRelationshipFields($params, $this->query);
        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());

        $this->query->prepareReadQuery($this->getModelPath(), $params, $this->relationship);
        $this->executeQuery($params, 'prepareReadQuery');

        return $this->resultSets;
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

        $this->instantiateQuery($params);

        $this->relationship->setRelationshipFields($params, $this->query);
        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());

        $this->query->prepareReadAllQuery($this->getModelPath(), $params, $this->relationship);

        $this->executeQuery($params, 'prepareReadAllQuery');

        return $this->resultSets;
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
        }
        else {
            $params['fields'] = $related . '.*';
        }

        $this->instantiateQuery($params);

        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());
        $this->query->prepareReadQuery($this->getModelPath(), $params, $this->relationship);
        $this->executeQuery($params, 'prepareReadQuery', false);

        return $this->resultSets;
    }

    /**
     * This function is called inside read or readAll method in order to perform queries. If
     * splitQueries flag is set to true and there are clauses for related model having type of
     * hasManyToMany, then those related models are fetched first and then base model. After that
     * the remaining related models (hasManyToMany) are fetched.
     *
     * @param array $params
     * @param string $typeOfQueryToPerfrom This is type of query to be performed on model.
     * @param bool $splitQuery
     */
    public function executeQuery($params, $typeOfQueryToPerform, $splitQuery = true)
    {
        $splitQuery = ($this->splitQueries && $splitQuery) ? $this->passSplittingChecks($params, $this->query) : false;

        if ($splitQuery) {
            $this->instantiateQuery($params);
            $hasManyToManyRels = $this->relationship->getRelationshipsAccordingToType('hasManyToMany');

            $filteredRels = $this->query->clause->prepareRelatedWhere($this->relationship);
            foreach ($filteredRels as $relName => $meta) {
                $this->resultSets[$relName] = $this->executeHasManyWithClause($relName, $meta, $hasManyToManyRels);
            }

            //**Executing Base Model **/
            $requiredRelationships = ['hasOne', 'hasMany', 'belongsTo'];
            $this->relationship->setRequiredRelationships($requiredRelationships);
            $this->relationship->setRelationshipFields($params, $this->query);
            $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());
            $this->query->{ $typeOfQueryToPerform}($this->getModelPath(), $params, $this->relationship);
            $this->executeModel();

            //If hasManyToMany relationships are left behind then execute.
            if ($hasManyToManyRels) {
                /**
                 * extract base model ids, that will be used as an input in remaining
                 * many-to-many rels
                 */
                $baseModelIds = DataExtractor::extractModelIds($this->resultSets['baseModel']);

                //execute remaining many-many relationships (rels without any clause)
                foreach ($hasManyToManyRels as $relName) {
                    $relMeta = $this->relationship->getRelationship($relName);
                    $this->resultSets[$relName] = $this->executeHasManyWithOutClause($relName, $baseModelIds, $relMeta);
                }
            }
        }
        else {
            $this->executeModel();
        }
    }

    /**
     * This function executes base model.
     *
     */
    protected function executeModel()
    {
        $this->fireEvent("afterQuery");
        $phalconQuery = $this->query->getPhalconQuery();
        $this->resultSets['baseModel'] = $phalconQuery->execute();
    }

    /**
     * This function executes related model of type hasManyToMany.
     *
     * @param string $relName Name of relationship.
     * @param array $baseModelIds Ids of base model (queried first).
     * @param array $relMeta Metadata of relationship.
     * @return array
     */
    protected function executeHasManyWithOutClause($relName, $baseModelIds, $relMeta)
    {
        $result = $this->executeHasManyRel($relName, $relMeta, $baseModelIds, true);
        return $result;
    }

    /**
     * This function executes related model of type hasManyToMany that have clauses.
     *
     * @param string $relName Name of relationship.
     * @param array $meta Metdata of relationship.
     * @param array $hasManyToManyRels Array of relationships of type hasManyToMany.
     * @return array
     */
    protected function executeHasManyWithClause($relName, $meta, &$hasManyToManyRels)
    {
        list($result, $relQuery) = $this->executeHasManyRel($relName, $meta);

        //extract related ids and set it to original where clause
        $ids = DataExtractor::extractRelIds($relQuery->newRelatedAlias, $result, $meta['rhsKey']);
        $this->query->clause->updateBaseWhereWithIds($ids, $meta, $this->modelAlias);

        $index = array_search($relName, $hasManyToManyRels);
        unset($hasManyToManyRels[$index]);

        return $result;
    }

    /**
     * This function executes related model of type hasManyToMany.
     *
     * @param string $relName Name of relationship.
     * @param array $meta Metadata of relationship.
     * @param array $baseModelIds Ids of base model (queried first).
     * @param bool $prepareWhere This flag is for whether to perpare WHERE clause
     * related model or not. True is required on related model execution without any clauses.
     * @return array
     */
    protected function executeHasManyRel($relName, $meta, $baseModelIds = null, $prepareWhere = false)
    {
        $relQuery = $this->getQuery($relName);
        ($prepareWhere) && ($relQuery->clause->updateRelatedWhere($baseModelIds, $meta, $relName));
        $relQuery->prepareManyToMany($relName, $meta, $this->relationship);
        $phalconQuery = $relQuery->getPhalconQuery();
        $result = $phalconQuery->execute();
        return array("result" => $result,
            "relQuery" => $relQuery);
    }

    /**
     * This function is used to load all kind of things related to Relationship of a base model.
     *
     * @param array $params
     */
    protected function bootstrapRelationship($params)
    {
        $this->relationship = $this->getRelationship();
        $this->di->set('relationship', $this->relationship);
        $metadata = $this->di->get('metaManager')->getModelMeta($this->modelAlias);
        $this->relationship->prepareDefaultRels($params);
        $this->relationship->loadRelationships($metadata['relationships']);
        $this->relationship->verifyRelationships($params['rels']);
        $this->relationship->loadRequestedRelationships($params['rels']);
    }

    /**
     * This function creates new query and sets up relationships and clauses on that query.
     * 
     * @param array $params
     */
    protected function instantiateQuery($params)
    {
        $id = isset($params['id']) ?: null;
        $this->query = $this->getQuery($this->modelAlias, $id);
        $this->fireEvent("beforeQuery");
        $this->bootstrapRelationship($params);
        $this->query->prepareClauses($params, $this->query);
    }

    /**
     * This function verify all checks for splitting of a query.
     * 
     * @param array $params
     * @param \Gaia\Core\MVC\Models\Query $query
     * @return boolean
     */
    protected function passSplittingChecks($params, $query)
    {
        $queryMeta = $this->getQueryMeta();
        $queryMeta->loadQueryMeta($params, $this->query, $this->relationship);

        $checksPassed = false;

        //Total Checks => 6
        while (true) {
            /**
             * Check if total number of requested rels are less then 3 and total number of
             * hasManyToMany rels are greater then 4.
             * If these conditions met then we can split the query.
             */
            if ($queryMeta->getTotalNumberOfRelationship() <= 3 && $queryMeta->getTotalHasManyToManyRels() > 4) {
                $checksPassed = true;
            }
            else {
                break;
            }

            //Check if there is any OR condition used in where clause.
            if (in_array("OR", $queryMeta->getOperators())) {
                $checksPassed = false;
                break;
            }

            //Check if sorting is applied on some models or not.
            if ($queryMeta->checkQueryHasSort()) {
                $checksPassed = false;
                break;
            }

            //Check if grouping is applied on some models or not.
            if ($queryMeta->checkQueryHasGroupBy()) {
                $checksPassed = false;
                break;
            }

            //Check if aggregate functions are applied on fields or not.
            if ($queryMeta->checkQueryHasAggregateFunctions()) {
                $checksPassed = false;
                break;
            }

            //Check if there is any exclusive conditions used in join by model.
            if ($queryMeta->checkQueryHasExclusiveConditions()) {
                $checksPassed = false;
                break;
            }

            break;
        }

        return $checksPassed;
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
     * @param string $modelName
     * @param string $id
     * @return \Gaia\Core\MVC\Models\Query
     */
    public function getQuery($modelName, $id = null)
    {
        $query = new Query($this->getDI(), $modelName, $id);
        return $query;
    }

    /**
     * This function return new Relationship object.
     *
     * @return \Gaia\Core\MVC\Models\Relationship
     */
    public function getRelationship()
    {
        $relationship = new Relationship($this->getDI());
        return $relationship;
    }

    /**
     * This function return new Query Metadata object.
     *
     * @return \Gaia\Core\MVC\Models\Query\Meta
     */
    public function getQueryMeta()
    {
        $queryMeta = new QueryMeta($this->getDI());
        return $queryMeta;
    }

}
