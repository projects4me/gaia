<?php

namespace Gaia\Core\MVC\Models;

class Query
{
    protected $query;

    /**
     * The dependency injector used by this class
     *
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
        $this->query = $this->di->get('modelsManager')->createBuilder();
    }

    public function prepareReadQuery($params)
    {
        $this->query->andWhere($this->modelAlias . ".id = '" . $params['id'] . "'");
        // fetch all the relationships
        $relations = $this->getRelationships();
        $modelName = get_class($this);

        $this->beforeSetQuery($params);

        $this->query->from([$this->modelAlias => $modelName]);
        // prepare count statement
        if (isset($params['count']) && !empty($params['count'])) {
            $countStatement = explode("as", $params['count']);
            $count = 'COUNT(' . $countStatement[0] . ') as' . $countStatement[1];
            array_push($params['fields'], $count);
        }

        // if HAVING clause is requested then set it up
        if (isset($params['having']) && !empty($params['having'])) {
            $this->query->having($params['having']);
        }

        $this->query->from([$this->modelAlias => $modelName]);
        // setup the passed columns
        $this->query->columns($params['fields']);

        // if grouping is requested then set it up 
        if (isset($params['groupBy']) && !empty($params['groupBy'])) {
            $this->query->groupBy([$params['groupBy']]);
        }

        // if sorting is requested then set it up
        if (isset($params['sort']) && !empty($params['sort'])) {
            // if order is requested the use otherwise use the default one
            if (isset($params['order']) && !empty($params['order'])) {
                $this->query->orderBy($params['sort'] . ' ' . $params['order']);
            } else {
                $this->query->orderBy($params['sort']);
            }
        }

        // if pagination params are set then set them up
        if (isset($params['limit']) && !empty($params['limit'])) {
            if (isset($params['offset']) && !empty($params['offset'])) {
                $this->query->limit($params['limit'], $params['offset']);
            } else {
                $this->query->limit($params['limit']);
            }
        }

        // if condition is requested then set it up
        if (isset($params['where']) && !empty($params['where'])) {
            $where = $this->preProcessWhere($params['where']);
            $GLOBALS['logger']->debug(print_r($this->query->getWhere(), 1));
            if (empty($this->query->getWhere())) {
                $this->query->where($where);
            } else {
                $this->query->andWhere($where);
            }
        }

        // setup all the clauses related to relationships
        foreach ($params['rels'] as $relationship) {
            //check Many-Many and hasMany as well
            if (!isset($relations[$relationship]) || empty($relations[$relationship])) {
                throw new \Phalcon\Exception('Relationship ' . $relationship . " not found. Please check spellings or refer to the guides.");
            }

            // If hte relationtype is defined in the meta data then use that
            // otherwise use the leftJoin as the default
            if (isset($relations[$relationship]['relType'])) {
                $join = \Phalcon\Text::lower($relations[$relationship]['relType']) . "Join";
            } else {
                $join = 'leftJoin';
            }

            // based on the metadata setup the joins in order to fetch relationships
            if ($relations[$relationship]['type'] == 'hasManyToMany') {
                // for a many-many relationship two joins are required
                $relatedModel = $relations[$relationship]['relatedModel'];
                $relatedModelAlias = Util::classWithoutNamespace($relatedModel);
                $secondaryModel = $relations[$relationship]['secondaryModel'];

                //If an exclusive condition for related model is defined then use that
                if (isset($relations[$relationship]['rhsConditionExclusive'])) {
                    $relatedQuery = $relations[$relationship]['rhsConditionExclusive'];
                } else {
                    $relatedQuery = $this->modelAlias . '.' . $relations[$relationship]['primaryKey'] .
                        ' = ' . $relationship . $relatedModelAlias . '.' . $relations[$relationship]['rhsKey'];
                }

                //If an exclusive condition for secondary model is defined then use that
                if (isset($relations[$relationship]['lhsConditionExclusive'])) {
                    $secondaryQuery = $relations[$relationship]['lhsConditionExclusive'];
                } else {
                    $secondaryQuery = $relationship . '.' . $relations[$relationship]['secondaryKey'] .
                        ' = ' . $relationship . $relatedModelAlias . '.' . $relations[$relationship]['lhsKey'];
                }

                // if a condition is set in the metadata then use it
                if (isset($relations[$relationship]['condition'])) {
                    $relatedQuery .= ' AND (' . $relations[$relationship]['condition'] . ')';
                }

                $this->query->$join($relatedModel, $relatedQuery, $relationship . $relatedModelAlias);
                $this->query->$join($secondaryModel, $secondaryQuery, $relationship);
            } else {
                $relatedModel = $relations[$relationship]['relatedModel'];

                // If an exclusive condition is defined then use that
                if (isset($relations[$relationship]['conditionExclusive'])) {
                    $relatedQuery = $relations[$relationship]['conditionExclusive'];
                } else {
                    $relatedQuery = $this->modelAlias . '.' . $relations[$relationship]['primaryKey'] .
                        ' = ' . $relationship . '.' . $relations[$relationship]['relatedKey'];
                }

                // if a condition is set in the metadata then use it
                if (isset($relations[$relationship]['condition'])) {
                    $relatedQuery .= ' AND ' . $relations[$relationship]['condition'];
                }
                // for each relationship apply the relationship joins to phalcon query object
                $this->query->$join($relatedModel, $relatedQuery, $relationship);
            }
        }

        $GLOBALS['logger']->debug($this->query->getPhql());
    }

    public function getPhqlQuery()
    {
        return $this->query->getQuery();
    }

    public function getQuery()
    {
        return $this->query;
    }
}
