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
 * $relationship->setRequiredRelationships(['hasOne','hasMany']);
 * $relationship->setRelationshipFields($params);
 * $relationship->prepareJoinsForQuery($params['rels'], $this->modelAlias);
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
    public $modelRelationships = [];

    /**
     * The name of relationships of type ManyToMany.
     * 
     * @var array
     */
    public $hasManyToMany = [];

    /**
     * The name of relationships of type OneToMany.
     * 
     * @var array
     */
    public $hasMany = [];

    /**
     * The name of relationships of type OneToOne.
     * 
     * @var array
     */
    public $hasOne = [];

    /**
     * The name of relationships of type ManyToOne.
     * 
     * @var array
     */
    public $belongsTo = [];

    /**
     * This contains array of relationship fields.
     * 
     * @var array
     */
    public $relationshipFields = [];

    /**
     * This contains array of types of relationship. E.g if query is splitted
     * then required relationship types for base model to join in a single query
     * can be "hasOne" and "hasMany". By default these are set to all three types of
     * relationships.
     * 
     * @var array
     */
    public $requiredRelationshipTypes = ['hasOne', 'hasMany', 'hasManyToMany', 'belongsTo'];

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
     */
    public function setRelationshipFields(&$params, $splitQuery)
    {
        if (isset($params['fields']) && !empty($params['fields'])) {
            //if query split is true then split relationship fields according to requiredRelationshipTypes
            if ($splitQuery) {
                $fields = $params['fields'];

                //iterate requested relationships
                foreach ($params['rels'] as $relationshipName) {
                    $relationshipMeta = $this->getRelationship($relationshipName);

                    //Extract user requested fields for relationships
                    foreach ($fields as $field) {
                        list($requestedFieldRelationship, $fieldName) = explode('.', $field);

                        /**
                         * Check whether the relationship type is avalaible inside requiredRelationshipType array or not.
                         * If user has set requiredRelationshipTypes ==> ['hasOne','hasMany'] then
                         * we'll only set relationship fields of type hasManyToMany and will unset that field
                         * from $params['fields'] as we don't want that field in querying base model.
                         * 
                         */
                        if ($requestedFieldRelationship == $relationshipName
                        && !in_array($this->modelRelationships[$relationshipName]['type'], $this->requiredRelationshipTypes)) {
                            $this->relationshipFields[$requestedFieldRelationship][] = $this->changeAliasOfRel($relationshipMeta, $fieldName);
                            $fieldIndex = array_search($field, $params['fields']);
                            unset($params['fields'][$fieldIndex]);
                        }
                    }

                    /**
                     * If some related fields of hasManyToMany relationship are requested then check whether user has
                     * also requested "id" for related model. If not then set that.
                     */
                    if ($this->relationshipFields[$relationshipName]) {
                        $modelIdField = $this->changeAliasOfRel($relationshipMeta, 'id');
                        if (!in_array($modelIdField, $this->relationshipFields[$relationshipName])) {
                            $this->relationshipFields[$relationshipName][] = $modelIdField;
                        }

                        /**
                         * And also set middle table RHSKEY and LHSKEY as a required fields so that CONTROLLER can easily 
                         * extract and prepare information for base model.
                         */
                        $relatedModelName = Util::extractClassFromNamespace($relationshipMeta['relatedModel']);
                        $newRelatedAlias = "{$relationshipName}{$relatedModelName}";

                        $lhsKey = $relationshipMeta["lhsKey"];
                        $rhsKey = $relationshipMeta["rhsKey"];

                        $this->relationshipFields[$relationshipName][] = "{$newRelatedAlias}.{$lhsKey}";
                        $this->relationshipFields[$relationshipName][] = "{$newRelatedAlias}.{$rhsKey}";
                    }
                }
            }
        }
        // if fields are not set than set default fields ".*"
        else {
            foreach ($params['rels'] as $relationshipName) {
                $this->relationshipFields[] = $relationshipName . '.*';
            }
        }
    }

    /**
     * This function set the required relationship types.
     * 
     * @param array $params
     */
    public function setRequiredRelationships($relationshipTypes)
    {
        $this->requiredRelationshipTypes = $relationshipTypes;
    }

    /**
     * This function is used to prepare joins based on the relationship type. It creates a relationship
     * using factory pattern and calls that relationship's function in order to prepare join.
     *
     * @param array $requestedRels
     * @param string $modelAlias
     */
    public function prepareJoinsForQuery($requestedRels, $modelAlias)
    {
        foreach ($requestedRels as $relationshipName) {

            // If the relationtype is defined in the meta data then use that
            // otherwise use the leftJoin as the default
            if (isset($this->modelRelationships[$relationshipName]['relType'])) {
                $joinType = \Phalcon\Text::lower($this->modelRelationships[$relationshipName]['relType']);
            }
            else {
                $joinType = 'left';
            }

            $relType = $this->modelRelationships[$relationshipName]['type'];

            if (in_array($relType, $this->requiredRelationshipTypes)) {
                $relationshipType = $this->di->get('relationshipFactory')->createRelationship($relType);
                $relationshipType->prepareJoin($relationshipName, $this->modelRelationships[$relationshipName], $modelAlias, $joinType);
            }
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
     * This function change alias of relationship from a given query. 
     * e.g "skills.name : Emberjs" query for User model is requested so, it will change skills.name to Tag.name.
     * 
     * @param array $relMeta Metadata of relationship.
     * @param string $field Field of relationship.
     * 
     */
    public function changeAliasOfRel($relMeta, $field)
    {
        return Util::extractClassFromNamespace($relMeta['secondaryModel']) . '.' . $field;
    }
}
