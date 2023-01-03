<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Gaia\Core\MVC\Models\Relationship;
use Phalcon\Mvc\Model\MetaData;

/**
 * This class prepares phalcon supported queries (PHQL) by the given metadata.
 * 
 * Query object e.g $query is created inside model. Model calls $query functions e.g prepareReadQuery to build
 * up required query and then it execute that query to get desired result.
 * 
 * ```php
 * 
 * $query = new \Gaia\Core\MVC\Models\Query();
 * $query->prepareReadQuery($this->getModelPath(), $params);
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
     * @var $modelId
     */
    public $modelId;

    /**
     * Phalcon query builder.
     * 
     * @var $queryBuilder
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
     * @var string $modelAlias
     */
    protected $modelAlias;

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
        $this->modelAlias = $modelAlias;
        $this->di->set('queryBuilder', $this->queryBuilder);
    }

    /**
     * This function is used to prepare select query for an model. It firsts set the where clause 
     * in query builder if a model with an "id" is requested. Then it calls prepareSelectQuery 
     * function in order to create a query.
     * 
     * @param string $modelPath path of the model.
     * @param array $params Array of requested params.
     */
    public function prepareReadQuery($modelPath, $params)
    {
        $this->modelId = $params['id'];
        $this->queryBuilder->andWhere($this->modelAlias . ".id = '" . $params['id'] . "'");
        $this->prepareSelectQuery($modelPath, $params);
    }

    /**
     * This function is used to prepare select query for collection of model.
     *
     * @param string $modelPath path of the model.
     * @param array $params Array of requested params.
     */
    public function prepareReadAllQuery($modelPath, $params)
    {
        $this->prepareSelectQuery($modelPath, $params);
    }

    /**
     * This function is responsible to prepare select query for the model.
     *
     * @param string $modelPath path of the model.
     * @param array $params Array of requested params.
     */
    public function prepareSelectQuery($modelPath, $params)
    {
        $metadata = $this->di->get('metaManager')->getModelMeta($this->modelAlias);
        
        //setup relationship in query builder
        $this->relationship = new Relationship($this->di);
        $this->relationship->prepareDefaultRels($params);
        $this->relationship->loadRelationships($metadata['relationships']);
        $this->relationship->verifyRelationships($params);
        $this->relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias);

        $this->setFieldsForQuery($params);
        $this->beforePrepareQuery($params);

        $this->queryBuilder->from([$this->modelAlias => $modelPath]);
        // prepare count statement
        if (isset($params['count']) && !empty($params['count'])) {
            $countStatement = explode("as", $params['count']);
            $count = 'COUNT(' . $countStatement[0] . ') as' . $countStatement[1];
            array_push($params['fields'], $count);
        }

        // if HAVING clause is requested then set it up
        if (isset($params['having']) && !empty($params['having'])) {
            $this->queryBuilder->having($params['having']);
        }

        $this->queryBuilder->from([$this->modelAlias => $modelPath]);
        // setup the passed columns
        $this->queryBuilder->columns($params['fields']);

        // if grouping is requested then set it up 
        if (isset($params['groupBy']) && !empty($params['groupBy'])) {
            $this->queryBuilder->groupBy([$params['groupBy']]);
        }

        // if sorting is requested then set it up
        if (isset($params['sort']) && !empty($params['sort'])) {
            // if order is requested the use otherwise use the default one
            if (isset($params['order']) && !empty($params['order'])) {
                $this->queryBuilder->orderBy($params['sort'] . ' ' . $params['order']);
            } else {
                $this->queryBuilder->orderBy($params['sort']);
            }
        }

        // if pagination params are set then set them up
        if (isset($params['limit']) && !empty($params['limit'])) {
            if (isset($params['offset']) && !empty($params['offset'])) {
                $this->queryBuilder->limit($params['limit'], $params['offset']);
            } else {
                $this->queryBuilder->limit($params['limit']);
            }
        }

        // if condition is requested then set it up
        if (isset($params['where']) && !empty($params['where'])) {
            $where = $this->preProcessWhere($params['where'], $this->modelAlias);
            $GLOBALS['logger']->debug(print_r($this->queryBuilder->getWhere(), 1));
            if (empty($this->queryBuilder->getWhere())) {
                $this->queryBuilder->where($where);
            } else {
                $this->queryBuilder->andWhere($where);
            }
        }

        $GLOBALS['logger']->debug($this->queryBuilder->getPhql());
    }

    /**
     * This function set fields for query. If user defined some fields in request, then
     * those fields are retrieved. If not requested than all fields of model, alongwith 
     * relationship fields (if rels are given), are retrieved from database. 
     *
     * @param array $params Array of requested params.
     * @return array
     */
    public function setFieldsForQuery(&$params)
    {
        $moduleFields = $this->getModelFields();
        if (isset($params['fields']) && !empty($params['fields'])) {
            $params['fields'] = $params['fields'];
        } else if (isset($params['addRelFields']) && empty($params['addRelFields'])) {
            $params['fields'] = $moduleFields;
        } else {
            $params['fields'] = array_merge($moduleFields, $this->relationship->relationshipFields);
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
     * The purpose of this function is parse the query string from a string to
     * and array.
     *
     * <pre>
     * Allowed operators
     *  AND         And
     *  OR          Or
     *  BETWEEN     Between
     *  :           Equals to, = is not allowed for friendly URL parsing
     *  <           Less than
     *  >           Greater than
     *  <:          Less than or equals to
     *  >:          Grater than or equals to
     *  CONTAINS    Contains, acts as IN for CSVs and LIKE %% otherwise
     *  STARTS      Starts with
     *  ENDS        Ends with
     *  NULL        Is NULL
     *  EMPTY       Is empty
     *  !           Not, no space allowed after it
     *  (           Statement starts
     *  )           Statement ends
     *  ,           Multiple possible values
     *  '           String values
     * </pre>
     * Every statement must be comprised of substatement, each enclosed with
     * parenthesis. Substatements can only be enjoined using AND or OR
     * This condition is applied to reduce complex parsing improving the overall
     * time for processing the where statements
     *
     * The input needs to have the following format
     *
     * <code>
     *  ((Module.field CONTAINS data) AND (Module.field !: data))
     * </code>
     *
     * Note: If CONTAINS is passed multiple values then all values must be encapsulated in single quotes '.
     *
     * Otherwise there is no way to tell a string like "Yes,No" and "No, You don't" apart.
     *
     * You can also encapsulate a string in single quotes and send it but if the delimiter ',' are foung in the input
     * then it will be treated as multiple values.
     *
     * <code>
     *  (Module.field !BETWEEN rangeStart AND rangeEnd)
     * </code>
     *
     * <code>
     *  ((Module.field CONTAINS data1,data2,data3) AND (Module.field <: data))
     * </code>
     *
     * Note: Parenthesis are are allowed in a substatement.
     * Note: Subqueries are also not allowed.
     * Note: Apostrophe must be preceded with \.
     * @todo Allow : without spaces
     * @param string $statement
     * @return array
     * @throws \Phalcon\Exception
     */
    public function preProcessWhere($statement)
    {
        // Parsing ideology is simples, first extract all the sub statements
        // Then replace them in the queryString to get the exact
        //  \Phalcon\Mvc\Model compatible statement

        // process using another variable to retain the original statement
        $query = $statement;

        // ensure that the parenthesis are well formed
        if (substr_count($statement, '(') != substr_count($statement, ')')) {
            $errorStr = 'Invalid query, please refer to the guides. ' .
                'Please check the parenthesis in the query. ';
            if (substr_count($statement, '(') > substr_count($statement, ')')) {
                $errorStr .= 'You have forgotten ")"';
            } else {
                $errorStr .= 'You have forgotten "("';
            }
            throw new \Phalcon\Exception($errorStr);
        }
        // extract all the substatments based on parenthesis
        $expression = '@\([^(]*[^)]\)@';
        if (preg_match_all($expression, $statement, $matches)) {
            $substatements = $matches[0];
            foreach ($substatements as $substatement) {
                // Check for :,<,>,<:,>: in the string, if found then process
                // ignoring the spaces

                if (preg_match('@NULL|EMPTY@', $substatement)) {
                    $valueOffset = strlen($substatement) - 2;
                } else {
                    // Get the position for the second space in a substatement
                    $valueOffset = strpos($substatement, ' ', (strpos($substatement, ' ') + 1));
                }
                $value = substr($substatement, $valueOffset, (strlen($substatement) - $valueOffset - 1));

                list($field, $operator) = explode(' ', substr($substatement, 1, $valueOffset));

                // Trim three components of the substatement
                $operator = strtoupper(trim($operator));
                $field = trim($field);
                $value = trim($value);
                $value = trim($value, "'");

                if ($field == "{$this->modelAlias}.id") {
                    $this->modelId = $value;
                }

                $translatedStatement = '';
                // parse based on the operator
                switch ($operator) {
                    case ':':
                        $translatedStatement = "(" . $field . " = '" . $value . "')";
                        break;
                    case '!:':
                        $translatedStatement = "(" . $field . " != '" . $value . "')";
                        break;
                    case '>':
                        $translatedStatement = "(" . $field . " > '" . $value . "')";
                        break;
                    case '<':
                        $translatedStatement = "(" . $field . " < '" . $value . "')";
                        break;
                    case '>:':
                        $translatedStatement = "(" . $field . " >= '" . $value . "')";
                        break;
                    case '<:':
                        $translatedStatement = "(" . $field . " <= '" . $value . "')";
                        break;
                    case 'CONTAINS':
                        if (preg_match("@','@", $value)) {
                            $translatedStatement = "(" . $field . " IN ('" . $value . "'))";
                        } else {
                            $translatedStatement = "(" . $field . " LIKE '%" . $value . "%')";
                        }
                        break;
                    case 'STARTS':
                        $translatedStatement = "(" . $field . " LIKE '" . $value . "%')";
                        break;
                    case 'ENDS':
                        $translatedStatement = "(" . $field . " LIKE '%" . $value . "')";
                        break;
                    case '!CONTAINS':
                        if (preg_match("@','@", $value)) {
                            $translatedStatement = "(" . $field . " NOT IN ('" . $value . "'))";
                        } else {
                            $translatedStatement = "(" . $field . " NOT LIKE '%" . $value . "%')";
                        }
                        break;
                    case '!STARTS':
                        $translatedStatement = "(" . $field . " NOT LIKE '" . $value . "%')";
                        break;
                    case '!ENDS':
                        $translatedStatement = "(" . $field . " NOT LIKE '%" . $value . "')";
                        break;
                    case 'NULL':
                        $translatedStatement = "(" . $field . " IS NULL)";
                        break;
                    case '!NULL':
                        $translatedStatement = "(" . $field . " IS NOT NULL)";
                        break;
                    case 'EMPTY':
                        $translatedStatement = "(" . $field . " = '')";
                        break;
                    case '!EMPTY':
                        $translatedStatement = "(" . $field . " != '')";
                        break;
                    case 'BETWEEN':
                        list($upper, $lower) = explode(' AND ', $value);
                        $upper = trim($upper, "'");
                        $upper = trim($upper);
                        $lower = trim($lower, "'");
                        $lower = trim($lower);
                        $translatedStatement = "(" . $field . " BETWEEN '" . $upper . "' AND '" . $lower . "')";
                        break;
                    case '!BETWEEN':
                        list($upper, $lower) = explode(' AND ', $value);
                        $upper = trim($upper, "'");
                        $upper = trim($upper);
                        $lower = trim($lower, "'");
                        $lower = trim($lower);
                        $translatedStatement = "(!(" . $field . " BETWEEN '" . $upper . "' AND '" . $lower . "'))";
                        break;
                    default:
                        $translatedStatement = false;
                        break;
                }

                $query = str_replace($substatement, $translatedStatement, $query);

                // make sure that we were able to parse all substatements
                if (!$translatedStatement) {
                    throw new \Phalcon\Exception('Invalid query, please check the guides. ' .
                        'Most common issues are extra spaces and invalid operators, ' .
                        'please note that "=" is not allowed use ":" instead. ' .
                        'Possible issue in ' . $substatement);
                }
            }
        } else {
            throw new \Phalcon\Exception('Invalid query, please refer to guides. ' .
                'Query must have at least one sub-statement enclosed in parenthesis.');
        }

        return $query;
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
}
