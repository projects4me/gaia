<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

/**
 * This class represents the metadata of the requested query.
 * 
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Core\Mvc\Models\Query
 * @category Meta
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Meta
{
    /**
     * Name of models or relationships used in groupBy clause.
     * 
     * @var array
     */
    protected $groupByClauseModels = [];

    /**
     * Name of models or relationships used in sort clause.
     * 
     * @var array
     */
    protected $sortClauseModels = [];

    /**
     * The name of relationships of type ManyToMany.
     * 
     * @var array
     */
    protected $hasManyToMany = [];

    /**
     * The name of relationships of type OneToMany.
     * 
     * @var array
     */
    protected $hasMany = [];

    /**
     * The name of relationships of type OneToOne.
     * 
     * @var array
     */
    protected $hasOne = [];

    /**
     * The name of relationships of type ManyToOne.
     * 
     * @var array
     */
    protected $belongsTo = [];

    /**
     * Total number of joins of query.
     * 
     * @var int
     */
    protected $totalJoins;

    /**
     * Information related to Joins of query.
     * 
     * @var array
     */
    protected $joins = [];

    /**
     * Total number of requested relationships.
     * 
     * @var int
     */
    protected $totalRelationships;

    /**
     * This contains list of operators that are used in where clause.
     * 
     * @var array
     */
    protected $operators = [];

    /**
     * Total number of conditions used in where clause.
     * 
     * @var int
     */
    protected $totalConditionsInWhere;

    /**
     * This contains list aggregate functions used in fields.
     * 
     * @var array
     */
    protected $aggregateFunctions;

    /**
     * This contains exclusive conditions used in join for relationships.
     * 
     * @var array
     */
    protected $conditionExclusives;

    /**
     * This contains all where conditions as a key value pair, where key is the model
     * or relationship name.
     */
    protected $whereConditions;

    /**
     * Metadata constructor.
     *
     * @param \Phalcon\DiInterface $di
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * This function is responsible to load all metadata related to query.
     * 
     * @param array $params User requested params.
     * @param \Gaia\Core\MVC\Models\Query $query Model query.
     * @param \Gaia\Core\MVC\Models\Relationship $relationship
     */
    public function loadQueryMeta($params, $query, $relationship)
    {
        $this->loadWhereMeta($params, $query);
        $this->loadSortMeta($query);
        $this->loadGroupByMeta($query);
        $this->loadRelationshipMeta($params, $query, $relationship);
    }

    /**
     * This function loads meta related to sort clause.
     * 
     * @param \Gaia\Core\MVC\Models\Query $query Base model query.
     */
    protected function loadSortMeta($query)
    {
        $orderBy = $this->extractModelName($query->clause->orderBy);
        $this->orderByClauseModel = $orderBy;
    }

    /**
     * This function loads meta related to groupBy clause.
     * 
     * @param \Gaia\Core\MVC\Models\Query $query Base model query.
     */
    protected function loadGroupByMeta($query)
    {
        $groupByArray = $query->clause->groupBy;
        if (isset($groupByArray)) {
            foreach ($groupByArray as $groupBy) {
                $this->groupByClauseModels[] = $this->extractModelName($groupBy);
            }
        }
    }

    /**
     * This function loads meta related to where clause.
     * 
     * @param array $params User requested params.
     * @param \Gaia\Core\MVC\Models\Query $query Base model query.
     */
    protected function loadWhereMeta($params, $query)
    {
        $this->loadWhereClauseOperators($query->clause->where);
        $this->loadAggregateFunctions($params);
        $this->loadWhereConditions($query->clause->where);
    }

    /**
     * This functions loads all where conditions.
     */
    protected function loadWhereConditions($where)
    {
        preg_match_all('@\([^(]*[^)]\)@', $where, $matches);
        $substatements = $matches[0];
        foreach ($substatements as $substatement) {
            //regex for extracting model or rel name
            $regex = "/(?<=[(])[A-z]+/";
            preg_match($regex, $substatement, $modelName);

            $this->whereConditions[$modelName[0]][] = $substatement;
        }
    }

    /**
     * This function load all the operators used in where clause.
     * 
     * @param string $where
     */
    protected function loadWhereClauseOperators($where)
    {
        //extract operators used in where clause
        $regex = '@\([^(]*[^)]\)@';
        //now where clause will have only opening and closing brackets with some spaces, so remove these.
        $whereWithOutConditions = preg_replace($regex, '', $where);

        //set number of conditions used in where clause
        preg_match_all($regex, $where, $matches);
        $this->totalConditionsInWhere = $matches ? count($matches[0]) : 0;

        //extract list of unique operators 
        $regex = '@(\w+)(?!.*\1)@';
        preg_match_all($regex, $whereWithOutConditions, $matches);
        $this->operators = $matches[0] ? $matches[0] : array();
    }

    /**
     * This function loads all aggregate functions used by fields.
     * 
     * @param array $params User requested params.
     */
    protected function loadAggregateFunctions($params)
    {
        $aggregateFunctions = ["COUNT", "AVG", "SUM", "MAX", "MIN"];

        foreach ($params['fields'] as $field) {
            if (preg_match_all("/[A-z]+(?=[(])/", $field, $matches)) {
                $aggregateFunction = strtoupper($matches[0][0]);
                if (in_array($aggregateFunction, $aggregateFunctions)) {
                    $this->aggregateFunctions[] = $aggregateFunction;
                }
            }
        }
    }

    /**
     * This function loads meta related to relationship of model.
     * 
     * @param array $params User requested params.
     * @param \Gaia\Core\MVC\Models\Query $query Base model query.
     * @param \Gaia\Core\MVC\Models\Relationship $relationship
     */
    protected function loadRelationshipMeta($params, $query, $relationship)
    {
        $this->loadRequestedRelationships($relationship);
        $this->loadJoinsMeta($params, $query);
        $this->loadConditionExclusiveOfRels($params['rels']);
    }

    /**
     * This function loads meta related to joins of relationship.
     * 
     * @param array $params User requested params.
     * @param \Gaia\Core\MVC\Models\Query $query Base model query.
     */
    protected function loadJoinsMeta($params, $query)
    {
        //joins involved in query
        $this->joins = $query->getPhalconQueryBuilder()->getJoins();

        //total number of joins in a query
        $this->totalJoins = count($this->joins);
    }

    /**
     * This function fill array of different relationship types with relationship name.
     *
     * @param \Gaia\Core\MVC\Models\Relationship $relationship
     */
    protected function loadRequestedRelationships($relationship)
    {
        $this->hasOne = $relationship->getRelationshipsByType('hasOne');
        $this->hasManyToMany = $relationship->getRelationshipsByType('belongsTo');
        $this->hasManyToMany = $relationship->getRelationshipsByType('hasMany');
        $this->hasManyToMany = $relationship->getRelationshipsByType('hasManyToMany');
    }

    /**
     * This loads all exclusive conditions related to a relationship.
     * 
     * @param array $relationships Relationships requested.
     */
    protected function loadConditionExclusiveOfRels($relationships)
    {
        $relationship = $this->di->get("relationship");

        foreach ($relationships as $rel) {
            $relMeta = $relationship->getRelationship($rel);
            if (isset($relMeta['conditionExclusive'])) {
                $this->conditionExclusives[$rel] = $relMeta['conditionExclusive'];
            }

            if (isset($relMeta['lhsConditionExclusive'])) {
                $this->conditionExclusives[$rel] = $relMeta['lhsConditionExclusive'];
            }

            if (isset($relMeta['rhsConditionExclusive'])) {
                $this->conditionExclusives[$rel] = $relMeta['rhsConditionExclusive'];
            }
        }
    }

    /**
     * This function is used to extract modelName from a given field.
     * 
     * @param string $field
     * @return string
     */
    private function extractModelName($field)
    {
        $modelName = explode('.', $field);
        return $modelName[0];
    }

    /**
     * This functions returns operators used in query.
     * 
     * @return array
     */
    public function getOperators()
    {
        return $this->operators;
    }

    /**
     * This functions checks whether query has a sort clause or not.
     * 
     * @return boolean
     */
    public function checkQueryHasSort()
    {
        $sortExists = $this->sortClauseModels ? true : false;
        return $sortExists;
    }

    /**
     * This functions checks whether query has a group by clause or not.
     * 
     * @return boolean
     */
    public function checkQueryHasGroupBy()
    {
        $groupByExists = $this->groupByClauseModels ? true : false;
        return $groupByExists;
    }

    /**
     * This functions checks whether fields of a query use aggregate functions or not.
     * 
     * @return boolean
     */
    public function checkQueryHasAggregateFunctions()
    {
        $aggregateFunctionExists = $this->aggregateFunctions ? true : false;
        return $aggregateFunctionExists;
    }

    /**
     * This functions checks whether joins in a query use exclusive conditions or not.
     * 
     * @return boolean
     */
    public function checkQueryHasExclusiveConditions()
    {
        $aggregateFunctionExists = $this->aggregateFunctions ? true : false;
        return $aggregateFunctionExists;
    }

    /**
     * This functions returns total number of relationships of a query.
     * 
     * @return int
     */
    public function getTotalNumberOfRelationship()
    {
        return $this->totalRelationships;
    }

    /**
     * This functions returns total number of hasManyToMany relationships.
     * 
     * @return int
     */
    public function getTotalHasManyToManyRels()
    {
        return count($this->hasManyToMany);
    }
}
