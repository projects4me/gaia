<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Phalcon\Mvc\Model as PhalconModel;
use Foundation\metaManager;
use Phalcon\Mvc\Model\MetaData;


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
     * This is the query for this model, we are using this to allow behaviors to make changes if required
     *
     * @var $query
     * @type \Phalcon\Mvc\Model\Query\Builder
     */
    public $query;

    /**
     * The alias of the current model
     *
     * @var $modelAlias
     */
    protected $modelAlias;

    /**
     * This function is used in order to load the different behaviors that this model is
     * set to use
     *
     * @return void
     */
    public function loadBehavior()
    {
        // Load each of the relationship types one by one
        if (isset($this->metadata['behaviors']) && !empty($this->metadata['behaviors']))
        {
            foreach($this->metadata['behaviors'] as $behavior)
            {
                $behaviorClass = '\\Gaia\\MVC\\Models\\Behaviors\\' . $behavior;
                $this->addBehavior(new $behaviorClass);
            }
        }
    }

    /**
     * This function is responsible for loading all the relationships defined in the model's metadata
     *
     * @return void
     */
    protected function loadRelationships()
    {
        // Load each of the relationship types one by one
        if (isset($this->metadata['relationships']['hasOne']))
        {
            foreach($this->metadata['relationships']['hasOne'] as $alias => $relationship)
            {
                $this->hasOne(
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['relatedKey'],
                    array(
                        'alias' => $alias
                    )
                );
            }
        }

        // Load each of the relationship types one by many
        if (isset($this->metadata['relationships']['hasMany']))
        {
            foreach($this->metadata['relationships']['hasMany'] as $alias => $relationship)
            {
                $this->hasMany(
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['relatedKey'],
                    array(
                        'alias' => $alias
                    )
                );
            }
        }

        // Load each of the relationship types many by one
        if (isset($this->metadata['relationships']['belongsTo']))
        {
            foreach($this->metadata['relationships']['belongsTo'] as $alias => $relationship)
            {
                $this->belongsTo(
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['relatedKey'],
                    array(
                        'alias' => $alias
                    )
                );
            }
        }

        // Load each of the relationship types many by many
        if (isset($this->metadata['relationships']['hasManyToMany']))
        {
            foreach($this->metadata['relationships']['hasManyToMany'] as $alias => $relationship)
            {
                $this->hasManyToMany(
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['rhsKey'],
                    $relationship['lhsKey'],
                    $relationship['secondaryModel'],
                    $relationship['secondaryKey'],
                    array(
                        'alias' => $alias
                    )
                );
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
        $parts = explode('\\',get_class($this));
        $modelName = end($parts);
        $this->modelAlias = $modelName;

        $metadata = metaManager::getModelMeta($modelName);
        $this->setSource($metadata['tableName']);
        $this->metadata = $metadata;

        // Load the behaviors in the model as well
        $this->loadBehavior();
        $this->loadRelationships();
    }

    /**
     * This function returns the fields in the current model
     *
     * @return array
     */
    protected function getFields()
    {
        $fields = $this->metadata[MetaData::MODELS_ATTRIBUTES];
        foreach ($fields as &$field)
        {
            $field = $this->modelAlias.'.'.$field;
        }
        return $fields;
    }

    /**
     * This function returns relationship names for all relationships types or of the specified type
     *
     * @param mixed $type possible values are hasOne, hasMany, belongsTo and hasManyToMany
     * @return array
     */
    protected function getRelationships($type = false)
    {
        $relations = array();

        foreach($this->metadata['relationships'] as $relType => $relationships)
        {
            foreach($relationships as $name => $data)
            {

                if (!$type || $type == $relType)
                {
                    $data['type'] = $relType;
                    $relations[$name] = $data;
                }
            }
        }
        return $relations;
    }

    /**
     * This function is an alternate of \Phalcon\Mv\Model::find
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
        // Get fields and relationships
        $moduleFields = $this->getFields();
        $relationshipFields = array();
        $relations = array_merge($this->getRelationships('hasOne'),$this->getRelationships('belongsTo'));
        $relations = $this->getRelationships();

        // Prepare the default joins
        if(!isset($params['rels']) || (isset($params['rels']) && empty($params['rels'])))
        {
            $params['rels'] = array_keys($relations);
        }
        else if (isset($params['rels'][0]) && $params['rels'][0] == 'none')
        {
            $params['rels'] = array();
        }

        //Verify the relationships
        foreach($params['rels'] as $relationship)
        {
            if (!isset($relations[$relationship]) || empty($relations[$relationship]))
            {
                throw new \Phalcon\Exception('Relationship '.$relationship." not found. Please check spellings or refer to the guides. one-many and many-many are not supported in this call.");
            }

            if (!(isset($params['fields']) && !empty($params['fields'])))
            {
                $relationshipFields[] = $relationship.'.*';
            }
            else
            {
                if (!in_array($relationship.'.*', $params['fields']))
                {
                    $relationshipFields[] = $relationship.'.*';
                }
            }

        }

        // Set the fields
        if (isset($params['fields']) && !empty($params['fields']))
        {
            $params['fields'] = array_merge($params['fields'],$relationshipFields);
        }
        else
        {
            $params['fields'] = array_merge($moduleFields,$relationshipFields);
        }

        $query = $this->modelsManager->createBuilder();
        $query->andWhere($this->modelAlias.".id = '".$params['id']."'");

        // get the query
        $query = $this->setQuery($query,$params);




        // process ACL and other behaviors before executing the query
        $data = $query->execute();

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

        $this->fireEvent("beforeRead");//->fire("model:beforeRead", $this);

        // Get fields and relationships
        $moduleFields = $this->getFields();
        $relationshipFields = array();
        $relations = array_merge($this->getRelationships('hasOne'),$this->getRelationships('belongsTo'));
        $relations = $this->getRelationships();
        $userDefinedRelations = true;

        // Prepare the default joins
        if(!isset($params['rels']) || (isset($params['rels']) && empty($params['rels'])))
        {
            $params['rels'] = array_keys($relations);
        }
        else if (isset($params['rels'][0]) && $params['rels'][0] == 'none')
        {
            $params['rels'] = array();
        }

        if(isset($params['fields']) && !empty($params['fields']))
        {
            if(!is_array($params['fields']))
                $params['fields'] = explode (',', $params['fields']);
        }

        //Verify the relationships
        foreach($params['rels'] as $relationship)
        {
            if (!isset($relations[$relationship]) || empty($relations[$relationship]))
            {
                throw new \Phalcon\Exception('Relationship '.$relationship." not found. Please check spellings or refer to the guides. one-many and many-many are not supported in this call.");
            }

            if (!(isset($params['fields']) && !empty($params['fields'])))
            {
                $relationshipFields[] = $relationship.'.*';
            }
            else
            {
                if (!in_array($relationship.'.*', $params['fields']))
                {
                    $relationshipFields[] = $relationship.'.*';
                }
            }

        }

        // Set the fields
        if (isset($params['fields']) && !empty($params['fields']))
        {
            $params['fields'] = array_merge($params['fields'],$relationshipFields);
        }
        else
        {
            $params['fields'] = array_merge($moduleFields,$relationshipFields);
        }

        $this->query = $this->modelsManager->createBuilder();

        // get the query
        $this->query = $this->setQuery($this->query,$params);

        $this->fireEvent("beforeQuery");



        // process ACL and other behaviors before executing the query
        $data = $this->query->execute();

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
        // check for existance
        $related = $params['related'];
        $modelRelations = $this->getRelationships();

        //$relation = $modelRelations[$related];

        if (!isset($modelRelations[$related]) || empty($modelRelations[$related]))
        {
            throw new \Phalcon\Exception('Relationship '.$relationship." not found. Please check spellings or refer to the guides. one-many and many-many are not supported in this call.");
        }

        $params['rels'] = array($related);

        // Set the fields
        if (isset($params['fields']) && !empty($params['fields']))
        {
            $params['fields'] = $params['fields'];
        }
        else
        {
            $params['fields'] = $related.'.*';
        }

        $query = $this->modelsManager->createBuilder();

        $query->andWhere($this->modelAlias.".id = '".$params['id']."'");


        // get the query
        $query = $this->setQuery($query,$params);

        // process ACL and other behaviors before executing the query
        $data = $query->execute();

        return $data;
    }

    /**
     * Prepare the query for the model
     * Any behavior that must change the query can attach the 'after' event
     * with this function
     *
     * @param array $query
     * @param array $params
     * @return \Phalcon\Mvc\Model\Criteria
     * @throws \Phalcon\Exception
     */
    protected function setQuery($query,array $params)
    {
        // fetch all the relationships
        $relations = $this->getRelationships();
        $modelName = get_class($this);

        $query->from([$this->modelAlias => $modelName]);

        // setup the passed columns
        $query->columns($params['fields']);

        // if sorting is requested then set it up
        if (isset($params['sort']) && !empty($params['sort']))
        {
            // if order is requested the use otherwise use the default one
            if (isset($params['order']) && !empty($params['order']))
            {
                $query->orderBy($params['sort'].' '.$params['order']);
            }
            else
            {
                $query->orderBy($params['sort']);
            }
        }

        // if pagination params are set then set them up
        if (isset($params['limit']) && !empty($params['limit']))
        {
            if (isset($params['offset']) && !empty($params['offset']))
            {
                $query->limit($params['limit'], $params['offset']);
            }
            else
            {
                $query->limit($params['limit']);
            }
        }

        // if condition is requested then set it up
        if (isset($params['where']) && !empty($params['where']))
        {
            $where = self::preProcessWhere($params['where']);
            $query->where($where);
        }

        // setup all the clauses related to relationships
        foreach($params['rels'] as $relationship)
        {
            //check Many-Many and hasMany as well
            if (!isset($relations[$relationship]) || empty($relations[$relationship]))
            {
                throw new \Phalcon\Exception('Relationship '.$relationship." not found. Please check spellings or refer to the guides.");
            }

            // If hte relationtype is defined in the meta data then use that
            // otherwise use the leftJoin as the default
            if (isset($relations[$relationship]['relType']))
            {
                $join = \Phalcon\Text::lower($relations[$relationship]['relType'])."Join";
            }
            else
            {
                $join = 'leftJoin';
            }

            // based on the metadata setup the joins in order to fetch relationships
            if ($relations[$relationship]['type'] == 'hasManyToMany')
            {
                // for a many-many relationship two joins are required
                $relatedModel = $relations[$relationship]['relatedModel'];
                $secondaryModel = $relations[$relationship]['secondaryModel'];

                $relatedQuery = $this->modelAlias.'.'.$relations[$relationship]['primaryKey'].
                    ' = '.$relationship.$relatedModel.'.'.$relations[$relationship]['rhsKey'];
                $secondaryQuery = $relationship.'.'.$relations[$relationship]['secondaryKey'].
                    ' = '.$relationship.$relatedModel.'.'.$relations[$relationship]['lhsKey'];

                if (isset($relations[$relationship]['condition']))
                {
                    $relatedQuery .= ' AND ('.$relations[$relationship]['condition'].')';
                }

                $query->$join($relatedModel,$relatedQuery,$relationship.$relatedModel);
                $query->$join($secondaryModel,$secondaryQuery,$relationship);
            }
            else
            {
                $relatedModel = $relations[$relationship]['relatedModel'];

                // If an exclusive condition is defined then use that
                if (isset($relations[$relationship]['conditionExclusive']))
                {
                    $relatedQuery = $relations[$relationship]['conditionExclusive'];
                    $query->$join($relatedModel,$relatedQuery,$relationship);
                }
                else
                {
                    $relatedQuery = $this->modelAlias.'.'.$relations[$relationship]['primaryKey'].
                        ' = '.$relationship.'.'.$relations[$relationship]['relatedKey'];

                    // if a condition is set in the metadat then use it
                    if (isset($relations[$relationship]['condition']))
                    {
                        $relatedQuery .= ' AND '.$relations[$relationship]['condition'];
                    }

                    // for each relationship apply the relationship joins
                    $query->$join($relatedModel,$relatedQuery,$relationship);
                }
            }
        }

        return $query->getQuery();
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
    public static function preProcessWhere($statement)
    {
        // Parsing ideology is simples, first extract all the sub statements
        // Then replace them in the queryString to get the exact
        //  \Phalcon\Mvc\Model compatible statement

        // process using another variable to retain the original statement
        $query = $statement;

        // ensure that the parenthesis are well formed
        if(substr_count($statement, '(') != substr_count($statement, ')'))
        {
            $errorStr = 'Invalid query, please refer to the guides. '.
                'Please check the paranthesis in the query.';
            if (substr_count($statement, '(') > substr_count($statement, ')'))
            {
                $errorStr .= 'You have forgotten ")"';
            }
            else
            {
                $errorStr .= 'You have forgotten "("';
            }
            throw new \Phalcon\Exception($errorStr);
        }
        // extract all the substatments based on parenthesis
        $expression = '@\([^(]*[^)]\)@';
        if (preg_match_all($expression,$statement,$matches))
        {
            $substatements = $matches[0];
            foreach($substatements as $substatement)
            {
                // Check for :,<,>,<:,>: in the string, if found then process
                // ignoring the spaces

                if (preg_match('@NULL|EMPTY@', $substatement))
                {
                    $valueOffset = strlen($substatement)-2;
                }
                else
                {
                    // Get the position for the second space in a substatement
                    $valueOffset = strpos($substatement, ' ',(strpos($substatement, ' ')+1));
                }
                $value = substr($substatement, $valueOffset,(strlen($substatement)-$valueOffset-1));

                list($field,$operator) = explode(' ',substr($substatement, 1,$valueOffset));

                // Trim three components of the substatement
                $operator = strtoupper(trim($operator));
                $field = trim($field);
                $value = trim($value);
                $value = trim($value,"'");
                $translatedStatement = '';
                // parse based on the operator
                switch ($operator)
                {
                    case ':':
                        $translatedStatement = "(".$field." = '".$value."')";
                        break;
                    case '!:':
                        $translatedStatement = "(".$field." != '".$value."')";
                        break;
                    case '>':
                        $translatedStatement = "(".$field." > '".$value."')";
                        break;
                    case '<':
                        $translatedStatement = "(".$field." < '".$value."')";
                        break;
                    case '>:':
                        $translatedStatement = "(".$field." >= '".$value."')";
                        break;
                    case '<:':
                        $translatedStatement = "(".$field." <= '".$value."')";
                        break;
                    case 'CONTAINS':
                        if (preg_match("@','@",$value))
                        {
                            $translatedStatement = "(".$field." IN ('".$value."'))";
                        }
                        else
                        {
                            $translatedStatement = "(".$field." LIKE '%".$value."%')";
                        }
                        break;
                    case 'STARTS':
                        $translatedStatement = "(".$field." LIKE '".$value."%')";
                        break;
                    case 'ENDS':
                        $translatedStatement = "(".$field." LIKE '%".$value."')";
                        break;
                    case '!CONTAINS':
                        if (preg_match("@','@",$value))
                        {
                            $translatedStatement = "(".$field." NOT IN ('".$value."'))";
                        }
                        else
                        {
                            $translatedStatement = "(".$field." NOT LIKE '%".$value."%')";
                        }
                        break;
                    case '!STARTS':
                        $translatedStatement = "(".$field." NOT LIKE '".$value."%')";
                        break;
                    case '!ENDS':
                        $translatedStatement = "(".$field." NOT LIKE '%".$value."')";
                        break;
                    case 'NULL':
                        $translatedStatement = "(".$field." IS NULL)";
                        break;
                    case '!NULL':
                        $translatedStatement = "(".$field." IS NOT NULL)";
                        break;
                    case 'EMPTY':
                        $translatedStatement = "(".$field." = '')";
                        break;
                    case '!EMPTY':
                        $translatedStatement = "(".$field." != '')";
                        break;
                    case 'BETWEEN':
                        list($upper,$lower) = explode(' AND ',$value);
                        $upper = trim($upper,"'");
                        $upper = trim($upper);
                        $lower = trim($lower,"'");
                        $lower = trim($lower);
                        $translatedStatement = "(".$field." BETWEEN '".$upper."' AND '".$lower."')";
                        break;
                    case '!BETWEEN':
                        list($upper,$lower) = explode(' AND ',$value);
                        $upper = trim($upper,"'");
                        $upper = trim($upper);
                        $lower = trim($lower,"'");
                        $lower = trim($lower);
                        $translatedStatement = "(!(".$field." BETWEEN '".$upper."' AND '".$lower."'))";
                        break;
                    default :
                        $translatedStatement = false;
                        break;
                }

                $query = str_replace($substatement, $translatedStatement, $query);

                // make sure that we were able to parse all substatements
                if (!$translatedStatement)
                {
                    throw new \Phalcon\Exception('Invalid query, please check the guides. '.
                        'Most common issues are extra spaces and invalid operators, '.
                        'please note that "=" is not allowed use ":" instead. '.
                        'Possible issue in '.$substatement);
                }
            }
        }
        else
        {
            throw new \Phalcon\Exception('Invalid query, please refer to guides. '.
                'Query must have atleast one substatement eclosed in parenthesis.');
        }

        return $query;
    }
}
