<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Gaia\Libraries\Utils\Util;

/**
 * This class is used to handle things related to relationship of a model that includes loading, verification and
 * preparation of joins based on different type of relationships.
 * 
 * Relationship object is created inside query class. Query class calls relationship functions in order to load 
 * querybuilder's relationship things.
 * 
 * ```php
 * 
 * $relationship->prepareDefaultRels($params); 
 * $relationship->loadRelationships($metadata['relationships']);
 * $relationship->verifyRelationships($params);
 * $relationship->setRelationshipFields($params);
 * $relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias, $queryBuilder);
 * 
 * ```
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Core\Mvc\Models
 * @category Relationship
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Relationship
{
    /**
     * The name of relationships that are related to current model.
     * 
     * @var array
     */
    protected $modelRelationships = [];

    /**
     * This contains array of relationship fields.
     * 
     * @var array
     */
    protected $relationshipFields = [];

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
     * Relationship constructor.
     *
     * @param \Phalcon\DiInterface $di
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * This function extract name of requested relationships by splitting relationship names on the basis on ",". 
     * If no one is requested then these are set to an empty array.
     *
     * @param array $params
     */
    public function prepareDefaultRels(&$params)
    {
        if (!isset($params['rels']) || (isset($params['rels']) && empty($params['rels']))) {
            $params['rels'] = array_keys($this->modelRelationships);
        }
        else if (isset($params['rels'][0]) && $params['rels'][0] == 'none') {
            $params['rels'] = array();
        }
    }

    /**
     * This function loads relationship names that are related to current model.
     *
     * @param array $relationshipMeta
     */
    public function loadRelationships($relationshipsMeta)
    {
        foreach ($relationshipsMeta as $relType => $relationships) {
            foreach ($relationships as $name => $data) {
                $data['type'] = $relType;
                $this->modelRelationships[$name] = $data;
            }
        }
    }

    /**
     * This function checks that whether requested rels for a model are available or not. If rels are available then
     * it set fields of those relationships in $relationshipFields array that will be used in query class for fetching those
     * fields from database.
     *
     * @param array $params
     */
    public function verifyRelationships($relationships)
    {
        foreach ($relationships as $relationshipName) {
            if (!isset($this->modelRelationships[$relationshipName]) || empty($this->modelRelationships[$relationshipName])) {
                throw new \Phalcon\Exception('Relationship ' . $relationshipName . " not found. Please check spellings or refer to the guides.");
            }
        }
    }

    /**
     * This function set relationship fields. These are dependent upon type of relationships required and requested fields. 
     * For example if hasOne and hasMany relationships are required then this function only load the fields for those 
     * type of relationships. And if user give field e.g "members.*" for relationship, then it will only use those field. 
     *
     * @param array $params
     * @param \Gaia\Core\MVC\Models\Query $query
     */
    public function setRelationshipFields($relationships)
    {
        foreach ($relationships as $relationshipName) {
            $this->relationshipFields[] = $relationshipName . '.*';
        }
    }

    /**
     * This function is used to prepare joins based on the relationship type. It creates a relationship
     * using factory pattern and calls that relationship's function in order to prepare join.
     *
     * @param array $requestedRels
     * @param string $modelAlias
     * @param \Phalcon\Mvc\Model\Query\Builder $queryBuilder
     */
    public function prepareJoinsForQuery($requestedRels, $modelAlias, $queryBuilder)
    {
        foreach ($requestedRels as $relationshipName) {

            /**
             * If the relationtype is defined in the meta data then use that.
             * Otherwise use the leftJoin as the default
             */
            if (isset($this->modelRelationships[$relationshipName]['relType'])) {
                $joinType = \Phalcon\Text::lower($this->modelRelationships[$relationshipName]['relType']);
            }
            else {
                $joinType = 'left';
            }

            $relType = $this->modelRelationships[$relationshipName]['type'];

            $relationshipType = $this->di->get('relationshipFactory')->createRelationship($relType);
            $relationshipType->prepareJoin($relationshipName, $this->modelRelationships[$relationshipName], $modelAlias, $joinType, $queryBuilder);
        }
    }

    /**
     * This function fill array of different relationship types with relationship name. For example 
     * if a relationship named "members" is of type hasManyToMany then "hasManyToMany" array will be 
     * filled up with relationship name.
     *
     * @param array $relationships Array of relationships
     */
    public function loadRequestedRelationships($relationships)
    {
        foreach ($relationships as $relName) {
            $relType = $this->modelRelationships[$relName]['type'];
            $this->{ $relType}[] = $relName;
        }
    }

    /**
     * This function returns a relationship.
     *
     * @param string $relationshipName Name of the relationship
     */
    public function getRelationship($relationshipName)
    {
        $relationship = $this->modelRelationships[$relationshipName];
        return $relationship;
    }

    /**
     * This function return relationship fields.
     * 
     * @return array
     */
    public function getRelationshipFields()
    {
        return $this->relationshipFields;
    }

    /**
     * This function return relationships according to given type. e.g if User gives type => 'hasManyToMany', 
     * then this will return all hasManyToMany relationship names.
     * 
     * @param string $type
     * @return array
     */
    public function getRelationshipsByType($type)
    {
        return $this->{ $type};
    }

}