<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

use Gaia\Libraries\Utils\Util;

/**
 * This class represents the clauses for requested query.
 * 
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Core\Mvc\Models\Query
 * @category Clause
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Clause
{
    /**
     * Clause constructor.
     *
     * @param \Phalcon\DiInterface  $di
     */
    function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * This is the WHERE clause of a query.
     *
     * @var string
     */
    public $where = "";

    /**
     * This is ORDER BY clause of a query.
     *
     * @var string
     */
    public $orderBy = null;

    /**
     * This is GROUP BY clause of a query.
     *
     * @var string
     */
    public $groupBy = null;

    /**
     * This is HAVING clause of a query.
     *
     * @var string
     */
    public $having = null;

    /**
     * This flag represents whether hasManyToMany relationships are filtered 
     * by user or not.
     *
     * @var bool
     */
    public $filterHasManyToMany = false;

    /**
     * This contains array of relationships which user filtered.
     *
     * @var array
     */
    protected $filteredRels = [];

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
     * @throws \Phalcon\Exception
     */
    public function prepareWhere($statement)
    {
        //first condition
        if (isset($statement) && !empty($statement)) {
            /** 
             * Parsing ideology is simples, first extract all the sub statements
             * Then replace them in the queryString to get the exact
             * \Phalcon\Mvc\Model compatible statement
             */

            $baseModelQuery = $this->di->get('baseModelQuery');

            //retain original statement
            $this->where = $statement;

            // ensure that the parenthesis are well formed
            if (substr_count($statement, '(') != substr_count($statement, ')')) {
                $errorStr = 'Invalid query, please refer to the guides. ' .
                    'Please check the parenthesis in the query. ';
                if (substr_count($statement, '(') > substr_count($statement, ')')) {
                    $errorStr .= 'You have forgotten ")"';
                }
                else {
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
                    }
                    else {
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

                    if ($field == "{$baseModelQuery->modelAlias}.id") {
                        $baseModelQuery->modelId = $value;
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
                            }
                            else {
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
                            }
                            else {
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

                    $this->where = str_replace($substatement, $translatedStatement, $this->where);

                    // make sure that we were able to parse all substatements
                    if (!$translatedStatement) {
                        throw new \Phalcon\Exception('Invalid query, please check the guides. ' .
                            'Most common issues are extra spaces and invalid operators, ' .
                            'please note that "=" is not allowed use ":" instead. ' .
                            'Possible issue in ' . $substatement);
                    }
                    else {
                        $this->prepareRelatedWhere($translatedStatement, $field);
                    }
                }
            }
            else {
                throw new \Phalcon\Exception('Invalid query, please refer to guides. ' .
                    'Query must have at least one sub-statement enclosed in parenthesis.');
            }
        } // first condition ends here..
    }

    /**
     * This function prepare ORDER BY clause.
     * 
     * @param string $sort Field of model.
     * @param string $order Order of sorting e.g DESC or ASC.
     */
    public function prepareOrderBy($sort, $order)
    {
        $relationship = $this->di->get('relationship');
        // if sorting is requested then set it up
        if (isset($sort) && !empty($sort)) {
            $orderBy['field'] = $sort;
            $orderBy['order'] = "";

            // if order is requested then use, otherwise use the default (DESC)
            (isset($order) && !empty($order)) && ($orderBy['order'] = " {$order}");

            $result = extract($this->checkHasManyToMany($orderBy['field']));
            if ($result && $this->filterHasManyToMany) {
                $relField = $relationship->changeAliasOfRel($relMeta, $relField);

                $relMeta['orderBy'] = "{$relField}{$orderBy['order']}";
                $this->filteredRels[$relName] = $relMeta;
            }
            else {
                $this->orderBy = "{$orderBy['field']}{$orderBy['order']}";
            }

        }
    }

    /**
     * This function prepare HAVING clause.
     * 
     * @param string $having Model field.
     */
    public function prepareHaving($having)
    {
        if (isset($having) && !empty($having)) {
            $this->having = $having;
        }
    }

    /**
     * This function prepare GROUP BY clause.
     * 
     * @param string $groupBy Field of model
     */
    public function prepareGroupBy($groupBy)
    {
        $relationship = $this->di->get('relationship');
        if (isset($groupBy) && !empty($groupBy)) {

            //by extracting, we'll get $relMeta, $relName and $relField
            $result = extract($this->checkHasManyToMany($groupBy));
            if ($result && $this->filterHasManyToMany) {
                $relField = $relationship->changeAliasOfRel($relMeta, $relField);

                //currently handling only one groupBy clause
                $relMeta['groupBy'] = $relField;
                $this->filteredRels[$relName] = $relMeta;
            }
            else {
                $this->groupBy = [$groupBy];
            }
        }
    }

    /**
     * This function prepare WHERE clause for related query. This is used when user query on
     * hasManyToMany relationship.
     * 
     * @param string $translatedStatement Statement which is translated by prepareWhere() function. 
     * e.g statement that contains ':' => '=' conversion.
     * @param string $field Field of which user query.
     */
    public function prepareRelatedWhere($translatedStatement, $field)
    {
        $relationship = $this->di->get('relationship');
        //by extracting, we'll get $relMeta, $relName and $relField
        $result = extract($this->checkHasManyToMany($field));

        if ($result && $this->filterHasManyToMany) {

            //change 
            $relField = $relationship->changeAliasOfRel($relMeta, $relField);

            $statement = str_replace($field, $relField, $translatedStatement);

            //This work is for mutiple where conditions on same relationship.
            $precedingWhere = (isset($this->filteredRels[$relName]['where']) && !empty($this->filteredRels[$relName]['where']))
                ? ($this->filteredRels[$relName]['where']) : null;
            if ($precedingWhere) {
                $originalWhere = $this->filteredRels[$relName]['originalWhere'];
                $relMeta['originalWhere'] = "{$originalWhere} AND {$translatedStatement}";
                $relMeta['where'] = "{$precedingWhere} AND {$statement}";
            }
            else {
                $relMeta['where'] = $statement;
                $relMeta['originalWhere'] = $translatedStatement;
            }
            $this->filteredRels[$relName] = $relMeta;
        }
    }

    /**
     * This function checks whether the of given field is for hasManyToMany relationship or not.
     * 
     */
    public function checkHasManyToMany($field)
    {
        //First check whether the field is for model or not (either related or model is requested).
        $fieldParts = explode('.', $field);
        if ($this->di->get('baseModelQuery')->modelAlias != $fieldParts[0]) {

            /**
             * explode on '.' basis to get model or relationship name from field
             * e.g projects?query=(members.name : Rana Nouman) and members is a many-many relationship 
             * of projects. So variable $modelOrRelName will contain ["members"].
             */
            $relationship = $this->di->get('relationship')->getRelationship($fieldParts[0]);
            if ($relationship['type'] == 'hasManyToMany') {
                $this->filterHasManyToMany = true;
                return ["relMeta" => $relationship, "relName" => $fieldParts[0], "relField" => $fieldParts[1]];
            }
        }
        else {
            return array();
        }

    }

    /**
     * This function updates base model WHERE clause. This is used when query splitting is enabled and we got
     * a result set for hasManyToMany relationship.
     * 
     * @param array $ids Ids of the related model.
     * @param array $relMeta Metadata of relationship.
     * @param array $baseModel Alias of base model.
     */
    public function updateBaseWhereWithIds($ids, $relMeta, $baseModel)
    {
        $key = "{$baseModel}.{$relMeta['primaryKey']}";

        $statementToBeReplaced = $this->prepareINStatement($key, $ids);

        if (isset($relMeta['originalWhere'])) {
            $this->where = str_replace($relMeta['originalWhere'], $statementToBeReplaced, $this->where);
        }
        else {
            $this->where = $statementToBeReplaced;
        }
    }

    /**
     * This function is used to update where clause when user want to get model having specific id.
     * 
     * @param string $modelNamespace Namespace of model.
     * @param string $id Identifier of model.
     */
    public function updateBaseWhereWithId($modelNamespace, $id)
    {
        $modelName = Util::extractClassFromNamespace($modelNamespace);
        $this->where = "{$modelName}.id = '{$id}'";
    }

    /**
     * This function updates related model WHERE clause. This is used when we queried base model first and give ids
     * of those to query related model.
     * 
     * @param array $baseModelIds Ids of the base model.
     * @param array $relMeta Metadata of relationship.
     * @param string $relName Name of relationship.
     */
    public function updateRelatedWhere($baseModelIds, $relMeta, $relName)
    {
        $relatedModel = Util::extractClassFromNamespace($relMeta['relatedModel']);
        $key = "{$relName}{$relatedModel}.{$relMeta['rhsKey']}";

        $this->where = $this->prepareINStatement($key, $baseModelIds);
    }

    /**
     * This function prepare IN statement that will be used in WHERE clause.
     * 
     * 
     * @param string $key Name of field.
     * @param array $ids Array of ids.
     * @return string Prepared IN Statament.
     */
    private function prepareINStatement($key, $ids)
    {
        //preparing IN statement
        $inStatement = "({$key} IN(";

        if ($ids != null) {
            foreach ($ids as $id) {
                $inStatement .= "'{$id}',";
            }
            //remove last ',' from string
            $inStatement = substr($inStatement, 0, -1);

            //closing brackets
            $inStatement .= "))";
        }
        else {
            //closing bracket if there are no ids
            $inStatement .= '""))';
        }

        return $inStatement;
    }

    /**
     * This function returns list of filtered relationships.
     * 
     * @return array 
     */
    public function getFilteredRels()
    {
        return $this->filteredRels;
    }
}