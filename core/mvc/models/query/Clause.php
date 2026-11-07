<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

use Gaia\Exception\BadRequest;
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
    function __construct(\Phalcon\Di\FactoryDefault $di, WhereFieldValidator $fieldValidator = null)
    {
        $this->di = $di;
        $this->fieldValidator = $fieldValidator ?? new WhereFieldValidator($di);
    }

    /**
     * Placeholder prefix of the values bound by this clause (`:q0:`, `:q1:`, ...).
     */
    const BIND_PREFIX = 'q';

    /**
     * @var \Phalcon\Di\FactoryDefault
     */
    protected $di;

    /**
     * Checks the field of every where leaf.
     *
     * @var WhereFieldValidator
     */
    protected $fieldValidator;

    /**
     * Values bound to the placeholders of $where, keyed by placeholder name.
     *
     * @var array
     */
    protected $bind = [];

    /**
     * Number of placeholders handed out so far; names stay unique for the life of the clause.
     *
     * @var int
     */
    protected $bindCount = 0;

    /**
     * This is the WHERE clause of a query.
     *
     * @var string
     */
    public $where = "";

    /**
     * This is ORDER BY clause of a query.
     *
     * @var array
     */
    public $orderBy = null;

    /**
     * This is GROUP BY clause of a query.
     *
     * @var array
     */
    public $groupBy = null;

    /**
     * This is HAVING clause of a query.
     *
     * @var string
     */
    public $having = null;

    /**
     * This contains array of original and translated WHERE conditions.
     * 
     * @var array
     */
    protected $whereConditions = [];

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
     * Note: Values are bound as parameters, so an apostrophe needs no escaping (a legacy \' is read as ').
     * Note: Fields must be `field` or `alias.field` naming a field of the model or of one of its relationships;
     * anything else is a BadRequest (400).
     * @todo Allow : without spaces
     * @param string $statement
     * @param \Gaia\Core\MVC\Models\Query $query
     * @throws \Phalcon\Exception
     */
    public function prepareWhere($statement, $query)
    {
        //first condition
        if (isset($statement) && !empty($statement)) {
            /** 
             * Parsing ideology is simples, first extract all the sub statements
             * Then replace them in the queryString to get the exact
             * \Phalcon\Mvc\Model compatible statement
             */

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
                throw new BadRequest($errorStr);
            }
            // extract all the substatments based on parenthesis
            $expression = '@\([^(]*[^)]\)@';
            if (preg_match_all($expression, $statement, $matches)) {
                $substatements = $matches[0];
                $this->assertOnlyConnectorsBetween($substatements, $statement);
                foreach ($substatements as $substatement) {
                    // Check for :,<,>,<:,>: in the string, if found then process
                    // ignoring the spaces

                    if ($this->isNullaryLeaf($substatement)) {
                        $valueOffset = strlen($substatement) - 2;
                    }
                    else {
                        // Get the position for the second space in a substatement
                        $valueOffset = strpos($substatement, ' ', (strpos($substatement, ' ') + 1));
                    }
                    $value = substr($substatement, $valueOffset, (strlen($substatement) - $valueOffset - 1));

                    $head = ($valueOffset === false) ? [] : explode(' ', substr($substatement, 1, $valueOffset));
                    if (count($head) < 2) {
                        throw new BadRequest('Invalid query: cannot read a field and an operator in ' . $substatement);
                    }
                    list($field, $operator) = $head;

                    // Trim three components of the substatement
                    $operator = strtoupper(trim($operator));
                    $field = trim($field);
                    $value = trim($value);
                    $value = trim($value, "'");
                    // Values are bound, so apostrophes need no escaping; read a legacy \' as '.
                    $value = str_replace("\\'", "'", $value);

                    $this->fieldValidator->assertValid($field, $query->modelAlias);

                    if ($field == "{$query->modelAlias}.id") {
                        $query->modelId = $value;
                    }

                    $fieldParts = explode('.', $field);

                    $this->whereConditions['original'][$fieldParts[0]][] = $substatement;

                    $translatedStatement = $this->translateLeaf($field, $operator, $value, $query);

                    if ($translatedStatement) {
                        $this->whereConditions['translated'][$fieldParts[0]][] = $translatedStatement;
                        $this->where = $this->replaceFirst($substatement, $translatedStatement, $this->where);
                    }
                    // make sure that we were able to parse all substatements
                    if (!$translatedStatement) {
                        throw new BadRequest('Invalid query, please check the guides. ' .
                            'Most common issues are extra spaces and invalid operators, ' .
                            'please note that "=" is not allowed use ":" instead. ' .
                            'Possible issue in ' . $substatement);
                    }
                }
            }
            else {
                throw new BadRequest('Invalid query, please refer to guides. ' .
                    'Query must have at least one sub-statement enclosed in parenthesis.');
            }
        } // first condition ends here..
    }

    /**
     * Translate one parsed leaf into PHQL. Every value goes into the bind map; only the validated field and
     * fixed operators are written into the PHQL.
     *
     * @param string $field
     * @param string $operator Upper-cased operator token.
     * @param string $value
     * @param \Gaia\Core\MVC\Models\Query $query
     * @return string|false False when the operator is unknown.
     * @throws BadRequest
     */
    protected function translateLeaf($field, $operator, $value, $query)
    {
        $comparisons = ['>' => '>', '<' => '<', '>:' => '>=', '<:' => '<='];
        $likes = [
            'CONTAINS' => ['LIKE', '%', '%'],
            '!CONTAINS' => ['NOT LIKE', '%', '%'],
            'STARTS' => ['LIKE', '', '%'],
            '!STARTS' => ['NOT LIKE', '', '%'],
            'ENDS' => ['LIKE', '%', ''],
            '!ENDS' => ['NOT LIKE', '%', ''],
        ];

        switch ($operator) {
            case ':':
            case '!:':
                $negated = ($operator === '!:');
                if ($this->isNullSentinelValue($value)) {
                    return "(" . $field . ($negated ? " IS NOT NULL)" : " IS NULL)");
                }
                return "(" . $this->prepareComparisonField($field, $value, $query)
                    . ($negated ? " != " : " = ") . $this->bindValue($value) . ")";
            case '>':
            case '<':
            case '>:':
            case '<:':
                return "(" . $this->prepareComparisonField($field, $value, $query)
                    . " " . $comparisons[$operator] . " " . $this->bindValue($value) . ")";
            case 'CONTAINS':
            case '!CONTAINS':
                if (preg_match("@','@", $value)) {
                    $in = ($operator === 'CONTAINS') ? " IN " : " NOT IN ";
                    return "(" . $field . $in . "(" . $this->bindList(explode("','", $value)) . "))";
                }
                // fall through: a single value is a LIKE
            case 'STARTS':
            case '!STARTS':
            case 'ENDS':
            case '!ENDS':
                list($like, $before, $after) = $likes[$operator];
                $pattern = $before . $this->escapeLike($value) . $after;
                return "(" . $this->prepareLikeField($field, $query) . " " . $like . " " . $this->bindValue($pattern) . ")";
            case 'NULL':
                return "(" . $field . " IS NULL)";
            case '!NULL':
                return "(" . $field . " IS NOT NULL)";
            case 'EMPTY':
                return "(" . $field . " = '')";
            case '!EMPTY':
                return "(" . $field . " != '')";
            case 'BETWEEN':
            case '!BETWEEN':
                $range = explode(' AND ', $value);
                if (count($range) !== 2) {
                    throw new BadRequest('Invalid query: BETWEEN needs "start AND end" for ' . $field);
                }
                $between = $field . " BETWEEN " . $this->bindValue(trim(trim($range[0]), "'"))
                    . " AND " . $this->bindValue(trim(trim($range[1]), "'"));
                return ($operator === 'BETWEEN') ? "(" . $between . ")" : "(!(" . $between . "))";
            default:
                return false;
        }
    }

    /**
     * Only leaves reach the PHQL as translated text, so everything around them must be grouping and the
     * AND/OR connectors; e.g. `(Issue.subject : x) OR 1=1` is rejected.
     *
     * @param array  $substatements Leaves found in $statement.
     * @param string $statement     The query as sent.
     * @return void
     * @throws BadRequest
     */
    protected function assertOnlyConnectorsBetween(array $substatements, $statement)
    {
        $rest = $statement;
        foreach ($substatements as $substatement) {
            $rest = $this->replaceFirst($substatement, ' ', $rest);
        }

        if (!preg_match('/^(?:\s|\(|\)|\bAND\b|\bOR\b)*$/i', $rest)) {
            throw new BadRequest('Invalid query: only AND and OR may join the parenthesised conditions, got "'
                . trim($rest) . '".');
        }
    }

    /**
     * Record a value in the bind map and return its PHQL placeholder.
     *
     * @param string $value
     * @return string e.g. `:q3:`
     */
    protected function bindValue($value)
    {
        $name = self::BIND_PREFIX . $this->bindCount++;
        $this->bind[$name] = (string) $value;
        return ':' . $name . ':';
    }

    /**
     * Bind each value of a list and return the comma-separated placeholders.
     *
     * @param array $values
     * @return string e.g. `:q3:, :q4:`
     */
    protected function bindList(array $values)
    {
        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = $this->bindValue($value);
        }
        return implode(', ', $placeholders);
    }

    /**
     * Escape the LIKE wildcards in a value so it matches literally (backslash is the default LIKE escape).
     *
     * @param string $value
     * @return string
     */
    protected function escapeLike($value)
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Replace the first occurrence of $search, so repeated identical leaves each get their own translation.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    protected function replaceFirst($search, $replace, $subject)
    {
        $position = strpos($subject, $search);
        if ($position === false) {
            return $subject;
        }
        return substr_replace($subject, $replace, $position, strlen($search));
    }

    /**
     * The values bound to the placeholders still present in $where. Query splitting replaces leaves, so
     * values whose placeholder is gone are left out (PDO rejects unused parameters).
     *
     * @return array Placeholder name => value.
     */
    public function getBindParams()
    {
        $bind = [];
        foreach ($this->bind as $name => $value) {
            if (strpos((string) $this->where, ':' . $name . ':') !== false) {
                $bind[$name] = $value;
            }
        }
        return $bind;
    }

    /**
     * This function prepare ORDER BY clause.
     * 
     * @param string $sort Field of model.
     * @param string $order Order of sorting e.g DESC or ASC.
     */
    public function prepareOrderBy($sort, $order)
    {
        // if sorting is requested then set it up
        if (isset($sort) && !empty($sort)) {
            $orderBy['field'] = $sort;
            $orderBy['order'] = "";

            // if order is requested then use, otherwise use the default (DESC)
            (isset($order) && !empty($order)) && ($orderBy['order'] = " {$order}");

            $this->orderBy = "{$orderBy['field']}{$orderBy['order']}";
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
        if (isset($groupBy) && !empty($groupBy)) {
            $this->groupBy = [$groupBy];
        }
    }

    /**
     * This function returns array of relationship names which are filtered.
     * 
     * @return array Array of relationship names.
     */
    public function getFilteredRels()
    {
        return array_keys($this->whereConditions['original'] ?? []);
    }

    /**
     * Extract all leaf-level WHERE substatements from a translated where string.
     * Handles substatements with at most one level of inner parentheses
     * (e.g. plain conditions and CAST(... AS ...) forms alike).
     *
     * @param string $where
     * @return array
     */
    public function extractWhereSubstatements($where)
    {
        $expression = '@\([^()]*(?:\([^()]*\)[^()]*)*\)@';
        preg_match_all($expression, $where, $matches);
        return $matches[0] ?? [];
    }

    /**
     * Wrap non-text fields in CAST(... AS TEXT) for Postgres LIKE/NOT LIKE.
     *
     * @param string $field
     * @param \Gaia\Core\MVC\Models\Query $query
     * @return string
     */
    protected function prepareLikeField($field, $query)
    {
        if (!$this->isPostgresAdapter()) {
            return $field;
        }

        $type = $this->resolveFieldType($field, $query);
        if ($type === null || $this->isStringFieldType($type)) {
            return $field;
        }

        return 'CAST(' . $field . ' AS TEXT)';
    }

    /**
     * Wrap non-text fields in CAST(... AS TEXT) when the comparison value is
     * not valid for the column type. Postgres rejects e.g. bigint <> 'asdf'.
     *
     * @param string $field
     * @param string $value
     * @param \Gaia\Core\MVC\Models\Query $query
     * @return string
     */
    protected function prepareComparisonField($field, $value, $query)
    {
        if (!$this->isPostgresAdapter()) {
            return $field;
        }

        $type = $this->resolveFieldType($field, $query);
        if ($type === null || $this->isStringFieldType($type)) {
            return $field;
        }

        if ($this->isValueCompatibleWithType($value, $type)) {
            return $field;
        }

        return 'CAST(' . $field . ' AS TEXT)';
    }

    /**
     * Whether a leaf such as "(Issue.milestoneId !NULL)" uses a nullary operator.
     * Only the operator token (second word) decides, never text inside the value.
     *
     * @param string $substatement Leaf including its parentheses.
     * @return bool
     */
    protected function isNullaryLeaf($substatement)
    {
        $parts = explode(' ', trim(substr($substatement, 1, -1)), 3);
        if (count($parts) !== 2) {
            return false;
        }
        return in_array(strtoupper($parts[1]), ['NULL', '!NULL', 'EMPTY', '!EMPTY'], true);
    }

    /**
     * Frontend JS interpolation of undefined/null becomes these literals.
     *
     * @param string $value
     * @return bool
     */
    protected function isNullSentinelValue($value)
    {
        $normalized = strtolower(trim((string) $value));
        return $normalized === 'undefined' || $normalized === 'null';
    }

    /**
     * Whether $value can be compared against a column of $type without casting.
     *
     * @param string $value
     * @param string $type
     * @return bool
     */
    protected function isValueCompatibleWithType($value, $type)
    {
        if ($this->isNumericFieldType($type)) {
            return is_numeric($value);
        }

        return true;
    }

    /**
     * Whether a metadata field type is numeric.
     *
     * @param string $type
     * @return bool
     */
    protected function isNumericFieldType($type)
    {
        return in_array(
            strtolower((string) $type),
            array(
                'int',
                'integer',
                'bigint',
                'smallint',
                'tinyint',
                'mediumint',
                'float',
                'double',
                'decimal',
                'numeric',
                'real',
            ),
            true
        );
    }

    /**
     * Whether the configured database adapter is PostgreSQL.
     *
     * @return bool
     */
    protected function isPostgresAdapter()
    {
        if (!$this->di->has('config')) {
            return false;
        }

        $database = $this->di->get('config')->get('database');
        if (!$database) {
            return false;
        }

        $adapter = (string) $database['adapter'];

        return strcasecmp($adapter, 'Postgres') === 0
            || strcasecmp($adapter, 'Postgresql') === 0;
    }

    /**
     * Resolve metadata field type for Model.field or Rel.field.
     *
     * @param string $field
     * @param \Gaia\Core\MVC\Models\Query $query
     * @return string|null
     */
    protected function resolveFieldType($field, $query)
    {
        $parts = explode('.', $field);
        if (count($parts) !== 2 || !$this->di->has('metaManager')) {
            return null;
        }

        list($alias, $fieldName) = $parts;
        $metaManager = $this->di->get('metaManager');
        $modelName = $alias;

        if ($alias !== $query->modelAlias) {
            $relMeta = $metaManager->getRelationshipMeta($query->modelAlias, $alias, false);
            if (!empty($relMeta)) {
                $modelName = $metaManager->getRelatedModelName($query->modelAlias, $alias);
            }
        } else {
            $modelName = $query->modelAlias;
        }

        try {
            $meta = $metaManager->getModelMeta($modelName);
        } catch (\Exception $e) {
            return null;
        }

        if (!isset($meta['fields'][$fieldName]['type'])) {
            return null;
        }

        return $meta['fields'][$fieldName]['type'];
    }

    /**
     * Whether a metadata field type can be used with LIKE without casting.
     *
     * @param string $type
     * @return bool
     */
    protected function isStringFieldType($type)
    {
        return in_array(
            strtolower((string) $type),
            array('varchar', 'text', 'char', 'tinytext', 'mediumtext', 'longtext'),
            true
        );
    }

    /**
     * This function returns where clause according to given model name.
     * 
     * @param string $type
     * @param string $modelName
     */
    public function getWhereClause($type, $modelName)
    {
        $whereClauses = [];
        if (isset($this->whereConditions[$type][$modelName])) {
            $whereClauses = $this->whereConditions[$type][$modelName];
        }
        return $whereClauses;
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
        // No ids binds a single empty string, which matches no record.
        $values = ($ids != null) ? array_values((array) $ids) : [''];

        return "({$key} IN(" . $this->bindList($values) . "))";
    }

    /**
     * This function updates base model WHERE clause. This is used when query splitting is enabled and we got
     * a result set for hasManyToMany relationship.
     * 
     * @param array $ids Ids of the related model.
     * @param array $relMeta Metadata of relationship.
     * @param array $baseModel Alias of base model.
     */
    public function updateBaseWhereWithIds($ids, $relMeta, $baseModel, $statementToReplace)
    {
        $key = "{$baseModel}.{$relMeta['primaryKey']}";

        $statementToInject = $this->prepareINStatement($key, $ids);

        $this->where = $this->replaceFirst($statementToReplace, $statementToInject, $this->where);
    }

    /**
     * This function updates related model WHERE clause. This is used when we queried base model first and give ids
     * of those to query related model.
     * 
     * @param array $baseModelIds Ids of the base model.
     * @param array $relMeta Metadata of relationship.
     * @param string $relatedModelName Name of related model.
     */
    public function updateRelatedWhere($baseModelIds, $relMeta, $relatedModelName)
    {
        $key = "{$relatedModelName}.{$relMeta['rhsKey']}";

        $this->where = $this->prepareINStatement($key, $baseModelIds);
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
        $this->where = "{$modelName}.id = " . $this->bindValue($id);
    }
}