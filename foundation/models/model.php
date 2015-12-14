<?php

/* 
 * Projects4Me Community Edition is an open source project management software 
 * developed by PROJECTS4ME Inc. Copyright (C) 2015-2016 PROJECTS4ME Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 (GNU AGPL v3) as
 * published be the Free Software Foundation with the addition of the following 
 * permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF 
 * THE COVERED WORK IN WHICH THE COPYRIGHT IS OWNED BY PROJECTS4ME Inc., 
 * Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.
 * 
 * You should have received a copy of the GNU AGPL v3 along with this program; 
 * if not, see http://www.gnu.org/licenses or write to the Free Software 
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * You can contact PROJECTS4ME, Inc. at email address contact@projects4.me.
 * 
 * The interactive user interfaces in modified source and object code versions 
 * of this program must display Appropriate Legal Notices, as required under 
 * Section 5 of the GNU AGPL v3.
 * 
 * In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal 
 * Notices must retain the display of the "Powered by Projects4Me" logo. If the 
 * display of the logo is not reasonably feasible for technical reasons, the 
 * Appropriate Legal Notices must display the words "Powered by Projects4Me".
 */

namespace Foundation\Mvc;
use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\Model\Message;
use Phalcon\Mvc\Model\Validator\Uniqueness;
use Phalcon\Mvc\Model\Validator\InclusionIn;
use Foundation\metaManager;


/**
 * This class is the base model in the foundation framework and is used to
 * overwrite the default functionality of Phalcon\Mvc\Model in order to
 * introdcude manual meta-data extensions along with other changes
 * 
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation\Mvc
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Model extends \Phalcon\Mvc\Model
{
    protected $metadata;
    

        public function initialize()
    {
        $this->metadata = metaManager::getModelMeta(get_class($this));
        $this->loadRelationships();
    }
    
    /**
     * This function is responsibles for loading all the relationships defined
     * in the model meta data
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
        $metadata = metaManager::getModelMeta(get_class($this));
        $this->setSource($metadata['tableName']);
        return $metadata;
    }
    
    /**
     * For some reason the tableName being set in the function metaData gets
     * overridden so we are seting the table again when the object is being
     * constructed
     */
    public function onConstruct()
    {
        $metadata = metaManager::getModelMeta(get_class($this));
        $this->setSource($metadata['tableName']);    
    }
    
    public function read($params)
    {
        $query = $this->query();
        $query->columns($params['fields']);
        $query->orderBy($params['sort']);
        $query->limit($params['limit'], $params['offset']);
        $where = self::preProcessWhere($params['where']);
        $query->where($where);

        // process joins

        // process ACL and other behaviors before executing the query
        
        $data = $query->execute();
        return $data;
        //print_r($this->request->getQuery('query'));
        //$model = new \Notes;
/*        $params = array(
            'models'     => array($modelName),
            'columns'    => array('id', 'subject', 'body'),
            'conditions' => "subject LIKE '%2%' AND body LIKE '%POST%'",
            'order'      => array('subject', 'id'),
            'limit'      => 20,
            'offset'     => 0,
            // or 'limit' => array(20, 20),
        );
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder($params);   
        print $queryBuilder->getPhql();
        $query = $queryBuilder->getQuery();
        $query->setDI($this->di);
        
        //$query = $this->modelsManager->createQuery($queryBuilder->getPhql());
        $data  = $query->execute();        
        foreach($data as $index => $values)
        {
            print_r($index);
            print_r($values);
        }
 */
        //$query  = $model::query();
        /*
            ->columns(array('Notes.id', 'Notes.subject', 'Notes.body','Users.username'))
            ->leftJoin('Users')
            ->where("subject LIKE '%2%'")
            ->andWhere("body LIKE '%POST%'")
            ->order("subject")
            ->limit(20,0)
            ->execute();*/        
    }
    
    /**
     * The purpose of this function is parse the query string from a string to
     * and array
     * 
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
     *
     * Every statement must be comprised of substatement, each enclosed with 
     * parenthesis. Substatements can only be enjoined using AND or OR
     * This condition is applied to reduce complex parsing improving the overall
     * time for processing the where statements
     * 
     * The input needs to have the following format
     * <code>
     *  ((Module.field CONTAINS data) AND (Module.field !: data)) 
     * </code>
     * Note: If CONTAINS is passed multiple values then all values must be encapsulated in single quotes '.
     * Otherwise there is no way to tell a string like "Yes,No" and "No, You don't" apart.
     * You can also encapsulate a string in single quotes and send it but if the delimiter ',' are foung in the input
     * then it will be treated as multiple values.
     * <code>
     *  (Module.field !BETWEEN rangeStart AND rangeEnd) 
     * </code>
     * <code>
     *  ((Module.field CONTAINS data1,data2,data3) AND (Module.field <: data)) 
     * </code>
     * Note: Parenthesis are are allowed in a substatement.
     * Note: Subqueries are also not allowed.
     * Note: Apostrophe must be preceded with \
     * @todo Allow : without spaces
     * @param type $queryString
     * @return array
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
