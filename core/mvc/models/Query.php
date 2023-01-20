<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Gaia\Core\MVC\Models\Relationship;
use Phalcon\Mvc\Model\MetaData;
use Gaia\Libraries\Utils\Util;
use Gaia\Core\MVC\Models\Query\Clause;

/**
 * This class prepares phalcon supported queries (PHQL) by the given metadata.
 * 
 * Query object e.g $query is created inside model. Model calls $query functions e.g prepareReadQuery to build
 * up required query and then it execute that query to get desired result.
 * 
 * ```php
 * 
 * $query = new \Gaia\Core\MVC\Models\Query();
 * $query->prepareReadQuery($this->getmodelNamespace(), $params);
 * $phalconQuery = $query->getPhalconQuery() // getPhalconQuery() function will return Query object of Phalcon.
 * $data = $phalconQuery->execute();
 * 
 * ```
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Core\Mvc\Models
 * @category Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Query
{
    /**
     * This is the id of model being requested by user.
     * 
     * @var string
     */
    public $modelId;

    /**
     * Phalcon query builder.
     * 
     * @var \Phalcon\Mvc\Model\Query\Builder
     */
    protected $queryBuilder;

    /**
     * The dependency injector used by this class
     *
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    /**
     * Alias of model.
     *
     * @var string
     */
    public $modelAlias;

    /**
     * This is the new alias for related model.
     * 
     * @var string
     */
    public $newRelatedAlias;

    /**
     * Query constructor.
     *
     * @param \Phalcon\DiInterface  $di
     * @param string $modelAlias Alias of Model.
     */
    function __construct(\Phalcon\Di\FactoryDefault $di, $modelAlias)
    {
        $this->di = $di;
        $this->queryBuilder = $this->di->get('modelsManager')->createBuilder();
        $this->clause = $this->getQueryClause();
        $this->modelAlias = $modelAlias;
        $this->di->set('currentQueryBuilder', $this->queryBuilder);
        $this->di->set('queryClause', $this->clause);
    }

    public function getQueryClause()
    {
        $clause = new Clause($this->di);
        return $clause;
    }

    /**
     * This function is used to prepare select query for an model. It firsts set the where clause 
     * in query builder if a model with an "id" is requested. Then it calls prepareSelectQuery 
     * function in order to create a query.
     * 
     * @param string $modelNamespace namespace of the model.
     * @param array $params Array of requested params.
     */
    public function prepareReadQuery($modelNamespace, $params)
    {
        $this->modelId = $params['id'];
        $this->clause->updateBaseWhereWithId($modelNamespace, $this->modelId);
        $this->prepareSelectQuery($modelNamespace, $params);
    }

    /**
     * This function is used to prepare select query for collection of model.
     *
     * @param string $modelNamespace namespace of the model.
     * @param array $params Array of requested params.
     */
    public function prepareReadAllQuery($modelNamespace, $params)
    {
        $this->prepareSelectQuery($modelNamespace, $params);
    }

    /**
     * This function is responsible to prepare select query for the model.
     *
     * @param string $modelNamespace namespace of the model.
     * @param array $params Array of requested params.
     */
    public function prepareSelectQuery($modelNamespace, $params)
    {
        $this->di->set('currentQueryBuilder', $this->queryBuilder);

        $this->setFieldsForQuery($params);
        $this->beforePrepareQuery($params);

        $this->queryBuilder->from([$this->modelAlias => $modelNamespace]);
        // prepare count statement
        if (isset($params['count']) && !empty($params['count'])) {
            $countStatement = explode("as", $params['count']);
            $count = 'COUNT(' . $countStatement[0] . ') as' . $countStatement[1];
            array_push($params['fields'], $count);
        }

        $this->queryBuilder->columns($params['fields']);
        $this->queryBuilder->groupBy($this->clause->groupBy);
        $this->queryBuilder->orderBy($this->clause->orderBy);
        $this->queryBuilder->where($this->clause->where);
        $this->queryBuilder->having($this->clause->having);

        // if pagination params are set then set them up
        if (isset($params['limit']) && !empty($params['limit'])) {
            if (isset($params['offset']) && !empty($params['offset'])) {
                $this->queryBuilder->limit($params['limit'], $params['offset']);
            }
            else {
                $this->queryBuilder->limit($params['limit']);
            }
        }

        $GLOBALS['logger']->debug($this->queryBuilder->getPhql());
    }

    /**
     * This function call clause class functions in order to prepare clauses for query.
     * 
     * @param $params 
     */
    public function prepareClauses($params)
    {
        $this->clause = $this->di->get('queryClause');
        $this->clause->prepareWhere($params['where']);
        $this->clause->prepareOrderBy($params['sort'], $params['order']);

        //set these clauses when only when list of models are requested.
        if ($this->modelId) {
            $this->clause->prepareGroupBy($params['groupBy']);
            $this->clause->prepareHaving($params['having']);
        }
    }

    /**
     * This function set fields for query. If user defined some fields in request, then
     * those fields are retrieved. If not requested than all fields of model, alongwith 
     * relationship fields (if rels are given), are retrieved from database. 
     *
     * @param array $params Array of requested params.
     */
    public function setFieldsForQuery(&$params)
    {
        $moduleFields = $this->getModelFields();
        if (isset($params['fields']) && !empty($params['fields'])) {
            $params['fields'] = $params['fields'];
        }
        else if (isset($params['addRelFields']) && empty($params['addRelFields'])) {
            $params['fields'] = $moduleFields;
        }
        else {
            $params['fields'] = array_merge($moduleFields, $this->di->get('relationship')->relationshipFields);
        }
    }

    /**
     * This function returns the fields in the current model.
     *
     * @return array
     */
    public function getModelFields()
    {
        $metadata = $this->di->get('metaManager')->getModelMeta($this->modelAlias);
        $fields = $metadata[MetaData::MODELS_ATTRIBUTES];
        foreach ($fields as &$field) {
            $field = $this->modelAlias . '.' . $field;
        }
        return $fields;
    }

    /**
     * If we want to do some work before setting up query, then we have to put that 
     * logic inside this function and call that function in setQuery method.
     *
     * @param array $params Array of requested params.
     */
    public function beforePrepareQuery(array &$params)
    {
        // Check if user has requested "id", if not then set it.
        if (is_array($params['rels']) && !in_array("{$this->modelAlias}.id", $params['fields'])) {

            // if rels is given and id without its module is given e.g 'Module.id' is
            // not given then delete that id field and create 'Module.id' field
            $idIndex = array_search('id', $params['fields']);
            if ($idIndex) {
                unset($params['fields'][$idIndex]);
            }

            array_push($params['fields'], "{$this->modelAlias}.id");
        }
    }

    /**
     * This function returns query builder of Phalcon.
     *
     * @return \Phalcon\Mvc\Model\Query\Builder
     */
    public function getPhalconQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * This function returns query object of Phalcon.
     *
     * @return \Phalcon\Mvc\Model\Query
     */
    public function getPhalconQuery()
    {
        return $this->queryBuilder->getQuery();
    }

    /**
     * This function checks whether user filtered hasManyToMany relationships or not.
     * 
     * @return bool
     */
    public function checkRelIsFiltered()
    {
        return $this->clause->filterHasManyToMany;
    }

    /**
     * This function is responsible for preparation of related query of type hasManyToMany.
     * 
     * @param string $relName Name of relationship.
     * @param string $meta Metadata of relationship.
     */
    public function prepareManyToMany($relName, $meta)
    {
        $meta['relatedKey'] = $meta['lhsKey'];
        $relatedModel = Util::extractClassFromNamespace($meta['relatedModel']);

        /**
         * concatenating relName e.g skills with relatedModel e.g Tagged => skillsTagged that will be used as an
         * alias in JOIN.
         */
        $this->newRelatedAlias = "{$relName}{$relatedModel}";

        /**extracting modelAlias from model path for quering in it.
         * e.g \\Gaia\\MVC\\Models\\Tag --> Tag **/
        $modelAlias = Util::extractClassFromNamespace($meta['secondaryModel']);

        $this->queryBuilder->from([$modelAlias => $meta['secondaryModel']]);

        //setting both model fields
        $this->queryBuilder->columns(["{$modelAlias}.*", "{$this->newRelatedAlias}.*"]);

        $relationship = new \Gaia\Core\MVC\Models\Relationships\HasMany($this->di);

        $this->di->set('currentQueryBuilder', $this->queryBuilder);

        $relationship->prepareJoin($this->newRelatedAlias, $meta, $modelAlias, 'left');

        //Clauses of related models are already set on preparing clauses for base model.
        if (isset($meta['where']) && !empty($meta['where'])) {
            $this->queryBuilder->where($meta['where']);
        }
        else if ($this->clause->where) {
            $this->queryBuilder->where($this->clause->where);
        }

        if (isset($meta['groupBy']) && !empty($meta['groupBy'])) {
            $this->queryBuilder->groupBy([$meta['groupBy']]);
        }

        if (isset($meta['orderBy']) && !empty($meta['orderBy'])) {
            $this->queryBuilder->orderBy([$meta['orderBy']]);
        }
    }
}