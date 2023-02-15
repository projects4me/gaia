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
use Gaia\Core\MVC\Models\Relationships\HasMany;
use Gaia\Core\MVC\Models\Relationships\HasManyToMany;

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
        $this->query = $this->getQuery($this->modelAlias, $params);
        $this->query->prepareClauses($params, $this->query);

        $this->relationship = $this->bootstrapRelationship($params);
        $this->relationship->loadRequestedRelationships($params['rels']);
        $this->relationship->setRelationshipFields($params['rels'], $this->query);
        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());

        $this->query->prepareReadQuery($this->getModelPath(), $params, $this->relationship);
        // $this->fireEvent("beforeQuery");

        $resultSets = $this->executeQuery($params, 'prepareReadQuery');

        $this->fireEvent("afterQuery");

        return $resultSets;
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

        $this->query = $this->getQuery($this->modelAlias, $params);
        $this->query->prepareClauses($params, $this->query);

        $this->relationship = $this->bootstrapRelationship($params);
        $this->relationship->loadRequestedRelationships($params['rels']);
        $this->relationship->setRelationshipFields($params['rels'], $this->query);
        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());

        $this->query->prepareReadAllQuery($this->getModelPath(), $params, $this->relationship);
        $this->fireEvent("beforeQuery");

        $resultSets = $this->executeQuery($params, 'prepareReadAllQuery');

        $this->fireEvent("afterQuery");

        return $resultSets;
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

        $this->query = $this->getQuery($this->modelAlias, $params);

        $this->relationship = $this->bootstrapRelationship($params);
        $this->relationship->loadRequestedRelationships($params['rels']);
        $this->relationship->setRelationshipFields($params['rels'], $this->query);
        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());

        $this->query->prepareReadQuery($this->getModelPath(), $params, $this->relationship);

        $this->fireEvent("beforeQuery");
        $resultSets = $this->executeQuery($params, 'prepareReadQuery', false);

        $this->fireEvent("afterQuery");

        return $resultSets;
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
    public function executeQuery($params, $typeOfQueryToPerform, $splitQueryParam = true)
    {
        $splitQuery = ($this->splitQueries && $splitQueryParam) ? $this->passSplittingChecks($params, $this->query) : false;

        $resultSets = [];

        if ($splitQuery) {
            //retain original params
            $parameters = $params;

            //Name of hasManyToMany relationships that are requested
            $hasManyToManyRels = $this->relationship->getRelationshipsByType('hasManyToMany');
            $hasManyToMany = new HasManyToMany($this->di);

            foreach ($hasManyToManyRels as $relName) {
                $relMeta = $this->relationship->getRelationship($relName);
                $hasManyToMany->setFields($parameters, $relName, $relMeta, $hasManyToManyRels);
            }

            //array of filtered relationships
            $filteredRels = $this->query->getClause()->getFilteredRels($this->relationship, $params['where']);

            //loop each filtered hasManyToMany relationship and execute it.
            foreach ($filteredRels as $filteredRel) {
                if (in_array($filteredRel, $hasManyToManyRels)) {
                    $resultSets[$filteredRel] = $this->executeHasManyRel($filteredRel, $parameters, $this->query);

                    //unsetting executed relationship
                    $index = array_search($filteredRel, $parameters['rels']);
                    unset($parameters['rels'][$index]);
                }
            }

            //unset remaining hasManyToMany rels and its fields from params
            $relsToBeExecuted = [];
            foreach ($hasManyToManyRels as $rel) {
                if (!in_array($rel, $filteredRels)) {
                    $index = array_search($rel, $parameters['rels']);
                    unset($parameters['rels'][$index]);
                    $relsToBeExecuted[] = $rel;
                }
            }

            //Execute Base Model
            $clause = $this->query->getClause();
            $this->query = $this->getQuery($this->modelAlias, $parameters);
            $this->query->setClause($clause);
            $this->relationship = $this->bootstrapRelationship($parameters);
            $this->relationship->setRelationshipFields($parameters['rels']);
            $this->relationship->prepareJoinsForQuery($parameters['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());
            $this->query->{ $typeOfQueryToPerform}($this->getModelPath(), $parameters, $this->relationship);
            $resultSets['baseModel'] = $this->executeModel($this->query);
            $baseModelIds = DataExtractor::extractModelIds($resultSets['baseModel']);

            //Execute remaining hasManyToMany rels
            foreach ($relsToBeExecuted as $relName) {
                $resultSets[$relName] = $this->executeHasManyRel($relName, $parameters, $this->query, $baseModelIds);
            }
        }
        else {
            $resultSets['baseModel'] = $this->executeModel($this->query);
        }

        return $resultSets;
    }

    /**
     * This function executes hasManyToMany relationships.
     * 
     * @param string $relName Name of relationship.
     * @param array $parameters
     * @param \Gaia\Core\MVC\Models\Query $baseModelQuery
     * @param array $baseModelIds
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function executeHasManyRel($relName, &$parameters, $baseModelQuery, $baseModelids = null)
    {
        $relParams = [];
        $keys = array_keys($parameters);
        $relParams = array_fill_keys($keys, $relParams);

        //Get relationship object
        $relationship = $this->getRelationship();

        //Get all where clauses related to relationship (without processing of where).
        $whereClauses = $baseModelQuery->getClause()->getWhereClause('original', $relName);

        //Get information related to relationship.
        $relMeta = $this->relationship->getRelationship($relName);
        $secondaryModelName = Util::extractClassFromNamespace($relMeta['secondaryModel']);
        $relatedModelName = Util::extractClassFromNamespace($relMeta['relatedModel']);

        //Prepare where for relationship
        $hasManyToManyRel = new HasManyToMany($this->di);
        if ($whereClauses) {
            $whereClause = $hasManyToManyRel->prepareWhere($this->relationship, $whereClauses);
            $relParams['where'] = $whereClause;
        }

        //We need to join related model, that's why related model added in rels.
        $relParams['rels'] = [$relatedModelName];

        $query = $this->getQuery($secondaryModelName, $relParams);
        $query->prepareClauses($relParams, $query);

        //If model is executed first then update relationship model where with model ids.
        if ($baseModelids) {
            $query->getClause()->updateRelatedWhere($baseModelids, $relMeta, $relatedModelName);
        }

        //Prepare Join for model
        $hasManyRel = new HasMany($this->di);
        $relMeta['relatedKey'] = $relMeta['lhsKey'];
        $hasManyRel->prepareJoin($relatedModelName, $relMeta, $secondaryModelName, 'left', $query->getPhalconQueryBuilder());

        //set default fields for relationship
        $relationship->setRelationshipFields($relParams['rels'], $query);

        //get all requested fields related to relationship.
        $relParams['fields'] = $hasManyToManyRel->getFields($relName);

        /**Execute Relationship */
        $query->prepareReadAllQuery($relMeta['secondaryModel'], $relParams, $relationship);
        $result = $this->executeModel($query);

        //Update Base model where clause
        $relWheres = $baseModelQuery->getClause()->getWhereClause('translated', $relName);
        foreach ($relWheres as $relWhere) {
            $ids = DataExtractor::extractRelIds($relatedModelName, $result, $relMeta['rhsKey']);
            $baseModelQuery->getClause()->updateBaseWhereWithIds($ids, $relMeta, $this->modelAlias, $relWhere);
        }

        return $result;
    }

    /**
     * This function executes base model.
     * 
     * @param \Gaia\Core\MVC\Models\Query $query
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    protected function executeModel($query)
    {
        $phalconQuery = $query->getPhalconQuery();
        return $phalconQuery->execute();
    }

    /**
     * This function is used to load all kind of things related to relationship of a model.
     *
     * @param array $params
     */
    protected function bootstrapRelationship($params)
    {
        $relationship = $this->getRelationship();
        $metadata = $this->di->get('metaManager')->getModelMeta($this->modelAlias);
        $relationship->prepareDefaultRels($params);
        $relationship->loadRelationships($metadata['relationships']);
        $relationship->verifyRelationships($params['rels']);
        return $relationship;
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

        if (($queryMeta->getTotalNumberOfRelationship() <= 3 && $queryMeta->getTotalHasManyToManyRels() > 4)
        && !(in_array("OR", $queryMeta->getOperators()))
        && !($queryMeta->checkQueryHasSort())
        && !($queryMeta->checkQueryHasGroupBy())
        && !($queryMeta->checkQueryHasAggregateFunctions())
        && !($queryMeta->checkQueryHasExclusiveConditions())) {
            $checksPassed = true;
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
     * @param array $params
     * @return \Gaia\Core\MVC\Models\Query
     */
    public function getQuery($modelName, $params)
    {
        $id = isset($params['id']) ? $params['id'] : null;
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
