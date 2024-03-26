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
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation\Mvc
 * @category Model
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
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
    protected $query;

    /**
     * This contains all information related to relationship of the model.
     *
     * @var \Gaia\Core\MVC\Models\Relationship
     */
    protected $relationship;

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
     * Flag to determine if ACL rules should be applied to the model.
     *
     * @var bool
     */
    protected $aclAllowed = true;

    /**
     * This contains behaviors that are required by the model.
     *
     * @var array
     */
    protected $requiredBehaviors = [];

    /**
     * This method is called only once when the model is created. We are loading
     * all behaviors related to model once.
     *
     * @return void
     */
    public function initialize()
    {
        $this->setRequiredBehaviors($this->getMetaData());
        $this->loadBehaviors($this->getRequiredBehaviors());
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
        $this->modelAlias = $this->getModelName();

        $metadata = $this->getMetaData();
        $this->setSource($metadata['tableName']);
        $this->metadata = $metadata;

        $this->keepSnapshots(true);
    }

    /**
     * This function is used in order to load the different behaviors that this model is
     * set to use.
     *
     * @param  array $behaviors
     * @return void
     */
    public function loadBehaviors($behaviors)
    {
        foreach ($behaviors as $behavior) {
            $behaviorClass = '\\Gaia\\MVC\\Models\\Behaviors\\' . $behavior;
            $this->addBehavior(new $behaviorClass());
        }
    }

    /**
     * This function is an alternate of \Phalcon\Mvc\Model::find
     * This RestController must use this function instead of find so that we can
     * support the default pagination, sorting, filtering and relationships
     *
     * @param  array $params
     * @return array
     * @throws \Phalcon\Exception
     * @todo   remove the relationship preload to all
     */
    public function read(array $params)
    {
        $this->query = $this->instantiateQuery($this->modelAlias, $params);
        $this->query->prepareClauses($params, $this->query);

        $this->relationship = $this->bootstrapRelationship($params);
        $this->relationship->loadRequestedRelationships($params['rels']);
        $this->relationship->setRelationshipFields($params['rels'], $this->query);

        $this->fireEvent('beforeJoins');
        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());

        $this->query->prepareReadQuery($this->getModelPath(), $params, $this->relationship);
        $this->fireEvent("beforeQuery");

        $resultSets = $this->executeQuery($params, 'prepareReadQuery');

        $this->fireEvent("afterQuery");

        return $resultSets;
    }

    /**
     * This function is an alternate of \Phalcon\Mv\Model::find
     * This RestController must use this function instead of find so that we can
     * support the default pagination, sorting, filtering and relationships
     *
     * @param  array $params
     * @return array
     * @throws \Phalcon\Exception
     */
    public function readAll(array $params)
    {
        $this->fireEvent("beforeRead");

        $this->query = $this->instantiateQuery($this->modelAlias, $params);
        $this->query->prepareClauses($params, $this->query);

        $this->relationship = $this->bootstrapRelationship($params);
        $this->relationship->loadRequestedRelationships($params['rels']);
        $this->relationship->setRelationshipFields($params['rels'], $this->query);

        $this->fireEvent('beforeJoins');
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
     * @param  array $params
     * @return array
     * @throws \Phalcon\Exception
     */
    public function readRelated(array $params)
    {
        $related = $params['related'];
        $params['rels'] = array($related);

        // Set the fields
        $params['fields'] = $related . '.*';
        if (isset($params['fields']) && !empty($params['fields'])) {
            $params['fields'] = $params['fields'];
        }

        $this->query = $this->instantiateQuery($this->modelAlias, $params);

        $this->relationship = $this->bootstrapRelationship($params);
        $this->relationship->loadRequestedRelationships($params['rels']);
        $this->relationship->setRelationshipFields($params['rels'], $this->query);

        $this->fireEvent('beforeJoins');
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
     * @param array  $params
     * @param string $typeOfQueryToPerfrom This is type of query to be performed on model.
     * @param bool   $splitQuery
     */
    public function executeQuery($params, $typeOfQueryToPerform, $splitQueryParam = true)
    {
        $splitQuery = ($this->splitQueries && $splitQueryParam) ? $this->passSplittingChecks($params) : false;
        $query = $this->getQuery();
        $relationship = $this->getRelationship();

        $resultSets = [];

        if ($splitQuery) {
            //retain original params
            $parameters = $params;

            //Name of hasManyToMany relationships that are requested
            $hasManyToManyRels = $relationship->getRelationshipsByType('hasManyToMany');
            $hasManyToMany = new HasManyToMany($this->di);

            foreach ($hasManyToManyRels as $relName) {
                $relMeta = $relationship->getRelationship($relName);
                $hasManyToMany->setFields($parameters, $relName, $relMeta, $hasManyToManyRels);
            }

            //array of filtered relationships
            $filteredRels = $query->getClause()->getFilteredRels($relationship, $params['where']);

            //loop each filtered hasManyToMany relationship and execute it.
            foreach ($filteredRels as $filteredRel) {
                if (in_array($filteredRel, $hasManyToManyRels)) {
                    $resultSets[$filteredRel] = $this->executeHasManyRel($filteredRel, $parameters);

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
            $clause = $query->getClause();
            $this->query = $this->instantiateQuery($this->modelAlias, $parameters);
            $this->query->setClause($clause);
            $this->relationship = $this->bootstrapRelationship($parameters);
            $this->relationship->setRelationshipFields($parameters['rels']);
            $this->relationship->prepareJoinsForQuery($parameters['rels'], $this->modelAlias, $this->query->getPhalconQueryBuilder());
            $this->query->{ $typeOfQueryToPerform}($this->getModelPath(), $parameters, $this->relationship);
            $resultSets['baseModel'] = $this->executeModel($this->query);
            $baseModelIds = DataExtractor::extractModelIds($resultSets['baseModel']);

            //Execute remaining hasManyToMany rels
            foreach ($relsToBeExecuted as $relName) {
                $resultSets[$relName] = $this->executeHasManyRel($relName, $parameters, $baseModelIds);
            }
        } else {
            $resultSets['baseModel'] = $this->executeModel($this->query);
        }

        return $resultSets;
    }

    /**
     * This function executes hasManyToMany relationships.
     *
     * @param  string $relName      Name of relationship.
     * @param  array  $parameters
     * @param  array  $baseModelIds
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function executeHasManyRel($relName, &$parameters, $baseModelids = null)
    {
        $relParams = [];
        $keys = array_keys($parameters);
        $relParams = array_fill_keys($keys, $relParams);
        $baseModelQuery = $this->getQuery();
        $baseModelRelationship = $this->getRelationship();

        //Get relationship object
        $relationship = $this->instantiateRelationship();

        //Get all where clauses related to relationship (without processing of where).
        $whereClauses = $baseModelQuery->getClause()->getWhereClause('original', $relName);

        //Get information related to relationship.
        $relMeta = $baseModelRelationship->getRelationship($relName);
        $secondaryModelName = Util::extractClassFromNamespace($relMeta['secondaryModel']);
        $relatedModelName = Util::extractClassFromNamespace($relMeta['relatedModel']);

        //Prepare where for relationship
        $hasManyToManyRel = new HasManyToMany($this->di);
        if ($whereClauses) {
            $whereClause = $hasManyToManyRel->prepareWhere($relMeta, $whereClauses);
            $relParams['where'] = $whereClause;
        }

        //We need to join related model, that's why related model added in rels.
        $relParams['rels'] = [$relatedModelName];

        $query = $this->instantiateQuery($secondaryModelName, $relParams);
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

        
        // Execute Relationship.
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
     * @param  \Gaia\Core\MVC\Models\Query $query
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
        $relationship = $this->instantiateRelationship();
        $metadata = $this->di->get('metaManager')->getModelMeta($this->modelAlias);
        $relationship->prepareDefaultRels($params);
        $relationship->loadRelationships($metadata['relationships']);
        $relationship->verifyRelationships($params['rels']);
        return $relationship;
    }

    /**
     * This function verify all checks for splitting of a query.
     *
     * @param  array $params
     * @return boolean
     */
    protected function passSplittingChecks($params)
    {
        $queryMeta = $this->instantiateQueryMeta();
        $queryMeta->loadQueryMeta($params, $this->getQuery(), $this->getRelationship());

        $checksPassed = false;

        if (($queryMeta->getTotalNumberOfRelationship() <= 3 && $queryMeta->getTotalHasManyToManyRels() > 4)
            && !(in_array("OR", $queryMeta->getOperators()))
            && !($queryMeta->checkQueryHasSort())
            && !($queryMeta->checkQueryHasGroupBy())
            && !($queryMeta->checkQueryHasAggregateFunctions())
            && !($queryMeta->checkQueryHasExclusiveConditions())
        ) {
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
     * @param  string $modelName
     * @param  array  $params
     * @return \Gaia\Core\MVC\Models\Query
     */
    public function instantiateQuery($modelName, $params)
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
    public function instantiateRelationship()
    {
        $relationship = new Relationship($this->getDI());
        return $relationship;
    }

    /**
     * This function returns relationship object.
     *
     * @return \Gaia\Core\MVC\Models\Relationship
     */
    public function getRelationship()
    {
        return $this->relationship;
    }

    /**
     * This function returns query object.
     *
     * @return \Gaia\Core\MVC\Models\Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * This function return new Query Metadata object.
     *
     * @return \Gaia\Core\MVC\Models\Query\Meta
     */
    public function instantiateQueryMeta()
    {
        $queryMeta = new QueryMeta($this->getDI());
        return $queryMeta;
    }

    /**
     * This function returns model name.
     *
     * @return string
     */
    public function getModelName()
    {
        return Util::extractClassFromNamespace($this->getModelPath());
    }

    /**
     * This function returns metadata of model.
     *
     * @return Array
     */
    public function getMetaData()
    {
        return $this->getDI()->get('metaManager')->getModelMeta($this->getModelName());
    }

    /**
     * This function is used to set the behaviors, required by the model.
     *
     * @param array $metadata
     */
    public function setRequiredBehaviors($metadata)
    {
        if (isset($metadata['behaviors']) && !empty($metadata['behaviors']) && (count($this->requiredBehaviors) == 0)) {
            $this->requiredBehaviors = $metadata['behaviors'];
        }
    }

    /**
     * This function returns array of requiredBehaviors
     *
     * @return array
     */
    public function getRequiredBehaviors()
    {
        return $this->requiredBehaviors;
    }

    /**
     * This function checks whether ACL functionality is enabled for the model by returning
     * the value of the $aclAllowed property.
     *
     * @return bool
     */
    public function isAclAllowed()
    {
        return $this->aclAllowed;
    }
}
